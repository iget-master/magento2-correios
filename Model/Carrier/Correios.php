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
    private $_freeZipRanges;
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
     * @var string
     */
    private $_sroLogin;

    /**
     * @var string
     */
    private $_sroPassword;


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
     * @return bool|\Magento\Framework\DataObject|\Magento\Shipping\Model\Rate\Result|null
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

        $isFreeShipping = $this->isFreeShipping($request);

        foreach ($packages as $package) {
            // @todo: encontrar uma forma de fazer o cache das respostas deste método
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

        }

        foreach ($methods as $methodCode => $method) {
            $rateMethod = $this->_rateMethodFactory->create();
            $rateMethod->setCarrier('correios');
            $rateMethod->setCarrierTitle($this->getConfig('carriers/correios/general/name') ?? "Correios");
            $rateMethod->setMethod('correios-' . $methodCode);
            $rateMethod->setMethodTitle($method['name']);

            if ($isFreeShipping && $this->_freeMethod == $methodCode) {
                $rateMethod->setPrice(0);

                if ($this->_freeShippingMessage) {
                    $rateMethod->setMethodTitle($method['name'] . " ({$this->_freeShippingMessage})");
                }

            } else {
                $rateMethod->setPrice($method['value'] + $this->_handlingFee);
            }

            $result->append($rateMethod);
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

    protected function isFreeShipping(RateRequest $request)
    {
        if ($request->getFreeShipping()) {
            return true;
        }

        $zip = preg_replace('/[^0-9]/', '', $this->_destinationPostCode);
        $value = $request->getPackageValue();

        foreach($this->_freeZipRanges as $zipRange) {
            if ($zip >= $zipRange['start'] && $zip <= $zipRange['end'] && $value > $zipRange['minimum_price']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $code
     * @return array
     */
    protected function _getTracking($code)
    {
        $_sroLogin = $this->getConfig('sro/login');
        $_sroPassword = $this->getConfig('sro/password');

        if (!$_sroLogin || !$_sroPassword) {
            return [];
        }

        return [
//            'url' => 'http://www.linkcorreios.com.br/?id='.$code,
            'progressdetail' => $this->_helper->getOnlineTracking($code, $_sroLogin, $_sroPassword),
        ];
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

        // Limit minimum dimensions
        $width = max($package['width'], 11);
        $height = max($package['height'], 2);
        $depth = max($package['depth'], 16);

        // Limit maximum dimensions
        $width = min($width, 90);
        $height = min($height, 90);
        $depth = min($depth, 90);

        if (count($this->_postingMethods)>0) {
            $_postingMethods = join(',', $this->_postingMethods);

            $url_d = static::WEBSERVICE_URL;

            if ($this->_login != "") {
                $url_d .= "&nCdEmpresa=" . $this->_login . "&sDsSenha=" . $this->_password;
            }
            $url_d .= "&nCdFormato=1&nCdServico=" . $_postingMethods . "&nVlComprimento=" .
                $depth . "&nVlAltura=" . $height . "&nVlLargura=" .
                $width . "&sCepOrigem=" . $this->_origPostcode . "&sCdMaoPropria=" .
                $this->_ownerHands . "&sCdAvisoRecebimento=" . $this->_proofOfDelivery . "&nVlPeso=" .
                $package['weight'] . "&sCepDestino=" . $this->_destinationPostCode;

            if ($this->_declaredValue) {
                $url_d .= "&nVlValorDeclarado=" . $package['value'];
            }

            $arrayConsult[] = $url_d;
            $this->_helper->logMessage($url_d);
        }

        return $arrayConsult;
    }

    /**
     * @param $key
     * @param string $namespace
     * @return mixed
     */
    protected function getConfig($key, $namespace = 'carriers/correios')
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
        $this->_handlingFee = (float) $this->getConfig('general/handling_fee') ?? 0;
        $this->_availableBoxes = $this->getConfig('packages/available_boxes');
        $this->_freeZipRanges = json_decode($this->getConfig('free_shipping/by_zip_range'), true);
        $this->_mergePackages = $this->getConfig('packages/merge_packages') != 0;
        $this->_login = $this->getConfig('contract/login');
        $this->_password = $this->getConfig('contract/password');
        $this->_postingMethods = $this->_helper->getPostMethodCodes();
        $this->_origPostcode = $this->getConfig('postcode', 'shipping/origin');
        $this->_freeMethod = $this->getConfig('post_methods/free_method');
    }
}
