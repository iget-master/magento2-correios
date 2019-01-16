<?php

/**
 * Correios
 *
 * Correios Shipping Method for Magento 2.
 *
 * @package Iget\Correios
 * @author Igor Ludgero Miura <igor@imaginemage.com>
 * @copyright Copyright (c) 2017 Imagination Media (http://imaginemage.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

namespace Iget\Correios\Model\Carrier;

use Iget\Correios\Model\Config\Source\FunctionMode;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Checkout\Model\Session;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\Result\Error;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Iget\Correios\Helper\Data;
use Iget\Correios\Model\CotacoesRepository;

class Correios extends AbstractCarrier implements CarrierInterface
{
    const WEBSERVICE_URL = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?StrRetorno=xml";

    protected $_code = 'correios';
    protected $_scopeConfig;
    protected $_storeScope;
    protected $_session;
    protected $_helper;
    protected $_enabled;
    protected $_destinationPostCode;
    protected $_weight;
    protected $_login;
    protected $_password;
    protected $_weightType;
    protected $_postingMethods;
    protected $_ownerHands;
    protected $_proofOfDelivery;
    protected $_declaredValue;
    protected $_packageValue;
    protected $_cubic;
    protected $_origPostcode;
    protected $_freeShipping;
    protected $_freeMethod;
    protected $_freeShippingMessage;
    protected $_statusFactory;
    protected $_handlingFee;
    private $_availableBoxes;
    private $_mergePackages;

    //Shipping Result
    protected $_result;
    /**
     * @var Error
     */

    protected $_resultError;
    /**
     * @var Status
     */
    protected $_tracking;

    /**
     * @var CotacoesRepository
     */
    protected $_cotacoes;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;


    /**
     * Correios constructor.
     * @param StatusFactory $statusFactory
     * @param Error $resultError
     * @param Status $resultStatus
     * @param Result $result
     * @param Session $session
     * @param Data $helperData
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param CotacoesRepository $_cotacoes
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        StatusFactory $statusFactory,
        Error $resultError,
        Status $resultStatus,
        Result $result,
        Session $session,
        Data $helperData,
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        CotacoesRepository $_cotacoes,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->_statusFactory = $statusFactory;
        $this->_helper = $helperData;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_session = $session;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeScope = ScopeInterface::SCOPE_STORE;
        $this->_result = $result;
        $this->_resultError = $resultError;
        $this->_tracking = $resultStatus;
        $this->_cotacoes = $_cotacoes;
        $this->_urlBuilder = $urlBuilder;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['correios' => "correios"];
    }

    /**
     * @param RateRequest $request
     * @return bool|\Magento\Framework\DataObject|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        $this->prepare();

        if ($this->_enabled == "0") {
            $this->_helper->logMessage("Module disabled");
            return false;
        }

        if (!$this->_helper->checkCountry(
            $request,
            $this->getConfig('country_id', 'shipping/origin')
        )) {
            $this->_helper->logMessage("Invalid Countries");
            return false;
        }

        $result = $this->_rateResultFactory->create();

        $this->_destinationPostCode = $this->_helper->formatZip($request->getDestPostcode());

        $packages = $this->_helper->getPackages($this->_session->getQuote());

        if ($this->_mergePackages) {
            $packages = $this->_helper->mergePackages($packages);
        }

        $methods = [];

        foreach ($packages as $package) {
            // @todo: Parei aqui. Não faz sentido que seja feita uma requisição à API para cada metodo de envio.
            // Também é interessante implementar um cache destas requisições, aos moldes do que o módulo base
            // tinha.
            $quotes = $this->_helper->getOnlineShippingQuotes(
                $this->generateRequestUrl($package)
            );

            foreach ($quotes as $quote) {
                $methodCode = (string) $quote['servico_codigo'];

                if (!array_key_exists($methodCode, $methods)) {
                    $methods[$methodCode] = [
                        'name' => 0,
                        'days' => 0,
                        'value' => 0,
                    ];
                }

                $methods[$methodCode]['name'] = $quote['servico'];
                $methods[$methodCode]['value'] += $quote['valor'];
                $methods[$methodCode]['days'] = max($quote['prazo'], $methods[$methodCode]['days']);
            }

            var_dump('package');
        }

        var_dump($methods);

        return $result;


//        $this->_packageValue = $request->getBaseCurrency()->convert(
//            $request->getPackageValue(),
//            $request->getPackageCurrency()
//        );



        if (!$this->_helper->checkWeightRange($request)) {
            $this->_helper->logMessage("Invalid Weight in checkWeightRange");
            return false;
        }

        if ($this->_helper->getCubicWeight($this->_session->getQuote()) == 0) {
            $this->_helper->logMessage("Invalid Weight in getCubicWeight");
            return false;
        } else {
            $this->_cubic = $this->_helper->getCubicWeight($this->_session->getQuote());
        }

        $correiosMethods = array();
        if (in_array($this->_functionMode, [
            FunctionMode::MODE_HYBRID,
            FunctionMode::MODE_ONLINE,
        ])) {
            $correiosMethods = $this->_helper->getOnlineShippingQuotes(
                $this->generateRequestUrl($request)
            );
        }

        if ($request->getFreeShipping() == true) {
            $this->_freeShipping = true;
        } else {
            $this->_freeShipping = false;
        }
        $invalidPostcodeChars = array("-",".");
        $postcodeNumber = str_replace($invalidPostcodeChars, "", $this->_destinationPostCode);
        //If not available online get offline
        if (($this->_functionMode == 2 && count($correiosMethods) != count($this->_postingMethods))
            || $this->_functionMode == 1) {
            $deliveryMessage = $this->_scopeConfig->getValue(
                "carriers/Iget_Correios/deliverydays_message",
                $this->_storeScope
            );
            if ($deliveryMessage=="") {
                $deliveryMessage = "%s - Em média %d dia(s)";
            }
            $showDeliveryDays = $this->_scopeConfig->getValue(
                "carriers/Iget_Correios/show_deliverydays",
                $this->_storeScope
            );
            $addDeliveryDays = (int)$this->_scopeConfig->getValue(
                "carriers/Iget_Correios/add_deliverydays",
                $this->_storeScope
            );
            foreach ($this->_postingMethods as $method) {
                $haveToGetOffline = true;
                foreach ($correiosMethods as $onlineMethods) {
                    if ($onlineMethods["servico"] == $method && ($onlineMethods["valor"] > 0 &&
                            $onlineMethods["prazo"]>0)) {
                        $haveToGetOffline = false;
                    }
                }
                if ($haveToGetOffline) {
                    if ($this->_cubic<=10) {
                        $correiosWeight = max($this->_weight, $this->_cubic);
                    } else {
                        $correiosWeight = $this->_cubic;
                    }

                    if (is_int($correiosWeight)==false) {
                        if ($correiosWeight > 0.5) {
                            $correiosWeight = round($correiosWeight);
                        } else {
                            $correiosWeight = 0.3;
                        }
                    }
                    $cotacaoOffline = $this->_cotacoes->getCollection()
                        ->addFieldToFilter('cep_inicio', ["lteq" => $postcodeNumber])
                        ->addFieldToFilter('cep_fim', ["gteq" => $postcodeNumber])
                        ->addFilter("servico", $method)
                        ->addFilter("peso", $correiosWeight)
                        ->getFirstItem();
                    if ($cotacaoOffline) {
                        if ($cotacaoOffline->getData()) {
                            if ($cotacaoOffline->getValor()>0) {
                                $data = array();
                                if ($showDeliveryDays==0) {
                                    $data['servico'] = $this->_helper->getMethodName($cotacaoOffline->getServico());
                                } else {
                                    $data['servico'] = sprintf(
                                        $deliveryMessage,
                                        $this->_helper->getMethodName($cotacaoOffline->getServico()),
                                        (int)$cotacaoOffline->getPrazo() + $addDeliveryDays
                                    );
                                }
                                $data['valor'] = str_replace(
                                    ",",
                                    ".",
                                    $cotacaoOffline->getValor()
                                ) + $this->_handlingFee;
                                $data['prazo'] = $cotacaoOffline->getPrazo() + $addDeliveryDays;
                                $data['servico_codigo'] = $cotacaoOffline->getServico();
                                $correiosMethods[] = $data;
                            }
                        }
                    }
                }
            }
        }
        foreach ($correiosMethods as $correiosMethod) {
            if ($correiosMethod["valor"] > 0 && $this->validateSameRegion($postcodeNumber, $correiosMethod['servico_codigo'])) {
                $method = $this->_rateMethodFactory->create();
                $method->setCarrier('correios');
                $method->setCarrierTitle($this->_scopeConfig->getValue(
                    'carriers/Iget_Correios/name',
                    $this->_storeScope
                ));
                $method->setMethod('correios' . $correiosMethod['servico_codigo']);
                if ($this->_freeShipping == true && $correiosMethod["servico_codigo"] == $this->_freeMethod) {
                    if ($this->_freeShippingMessage != "") {
                        $method->setMethodTitle("[" . $this->_freeShippingMessage . "] " . $correiosMethod['servico']);
                    } else {
                        $method->setMethodTitle($correiosMethod['servico']);
                    }
                    $amount = 0;
                } else {
                    $amount = $correiosMethod['valor'];
                    $method->setMethodTitle($correiosMethod['servico']);
                }
                $method->setPrice($amount);
                $method->setCost($amount);
                $result->append($method);
            }
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * @param $code
     * @return array
     */
    protected function _getTracking($code)
    {
        return array(
            'url' => 'http://www.linkcorreios.com.br/?id='.$code
        );
    }

    /**
     * @param $number
     * @return mixed
     */
    public function getTrackingInfo($number)
    {
        $aux = $this->_getTracking($number);
        $tracking = $this->_statusFactory->create();
        $tracking->setCarrier($this->_code);
        $tracking->setCarrierTitle("Correios");
        $tracking->setTracking($number);
        if ($aux!=false) {
            $tracking->addData($aux);
        }
        return $tracking;
    }

    /**
     * @param $package
     * @return array
     */
    protected function generateRequestUrl($package)
    {
        $arrayConsult = [];

        if (count($this->_postingMethods)>0) {
            foreach ($this->_postingMethods as $_method) {
                if ($this->_cubic<=10) {
                    $correiosWeight = max($this->_weight, $this->_cubic);
                } else {
                    $correiosWeight = $this->_cubic;
                }

                $url_d = static::WEBSERVICE_URL;

                if ($this->_login != "") {
                    $url_d .= "&nCdEmpresa=" . $this->_login . "&sDsSenha=" . $this->_password;
                }
                $url_d .= "&nCdFormato=1&nCdServico=" . $_method . "&nVlComprimento=" .
                    $package['depth'] . "&nVlAltura=" . $package['height'] . "&nVlLargura=" .
                    $package['width'] . "&sCepOrigem=" . $this->_origPostcode . "&sCdMaoPropria=" .
                    $this->_ownerHands . "&sCdAvisoRecebimento=" . $this->_proofOfDelivery . "&nVlPeso=" .
                    $package['weight'] . "&sCepDestino=" . $this->_destinationPostCode;

                if ($this->_declaredValue) {
                    $url_d .= "&nVlValorDeclarado=" . $package['value'];
                }

                $arrayConsult[] = $url_d;
                $this->_helper->logMessage($url_d);
            }
        }

        return $arrayConsult;
    }

    /**
     * @param $key
     * @param string $namespace
     * @return mixed
     */
    protected function getConfig($key, $namespace = 'carriers/Iget_Correios')
    {
        return $this->_helper->getConfig($key, $namespace);
    }

    /**
     * Load all necessary configurations
     */
    protected function prepare()
    {
        $this->_enabled = $this->getConfig('general/active');
        $this->_proofOfDelivery = $this->getConfig('general/proof_of_delivery') == 0 ? 'N' : 'Y';
        $this->_freeShippingMessage = $this->getConfig('general/free_shipping_message');
        $this->_declaredValue = $this->getConfig('general/declared_value');
        $this->_ownerHands = $this->getConfig('general/owner_hands') == 0 ? 'N' : 'Y';
        $this->_handlingFee = $this->getConfig('general/handling_fee') ?? 0;
        $this->_availableBoxes = $this->getConfig('packages/available_boxes');
        $this->_mergePackages = $this->getConfig('packages/merge_packages') != 0;
        $this->_login = $this->getConfig('contract/login');
        $this->_password = $this->getConfig('contract/password');
        $this->_postingMethods = $this->_helper->getPostMethodCodes();
        $this->_origPostcode = $this->getConfig('postcode', 'shipping/origin');
        $this->_freeMethod = $this->getConfig('post_methods/free_method');
    }
}
