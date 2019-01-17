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

namespace Iget\Correios\Helper;

use Iget\Correios\Model\ResourceModel\Cotacoes as ResourceModel;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class Data extends AbstractHelper
{
    /**
     * @var string
     */
    protected $storeScope;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var array
     */
    protected $obligatoryLogin = array(4162,40436,40444,81019,4669);

    /**
     * @var ResourceModel
     */
    protected $resourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var mixed
     */
    private $weightUnit;

    /**
     * Data constructor.
     * @param ProductRepository $productRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceModel $resourceModel
     */
    public function __construct(
        ProductRepository $productRepository,
        ScopeConfigInterface $scopeConfig,
        ResourceModel $resourceModel
    ) {
        $this->storeScope = ScopeInterface::SCOPE_STORE;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->resourceModel = $resourceModel;
        $writer = new Stream(BP . '/var/log/Iget_Correios.log');
        $this->logger = new Logger();
        $this->logger->addWriter($writer);
        $this->weightUnit = $this->getWeightUnit();
    }

    /**
     * @param (int)$method
     * @return string
     */
    public function getMethodName($method)
    {
        $method = (int)$method;
        if ($method === 40010 || $method === (int) $this->getconfig('custom_post_methods/sedex')) {
            return "Sedex";
        } elseif ($method === 41106 || $method === 4669 || $method === (int) $this->getconfig('custom_post_methods/pac')) {
            return "PAC";
        } elseif ($method === 40215 || $method === (int) $this->getconfig('custom_post_methods/sedex_10')) {
            return "Sedex 10";
        } elseif ($method === 40290 || $method === (int) $this->getconfig('custom_post_methods/sedex_hoje')) {
            return "Sedex HOJE";
        } elseif ($method === 40045 || $method === (int) $this->getconfig('custom_post_methods/sedex_cobrar')) {
            return "Sedex a cobrar";
        } else {
            return __("Unknown method");
        }
    }

    /**
     * @param $key
     * @param string $namespace
     * @return mixed
     */
    public function getConfig($key, $namespace = 'carriers/Iget_Correios')
    {
        return $this->scopeConfig->getValue(
            "{$namespace}/{$key}",
            $this->storeScope
        );
    }

    /**
     * @param $urlsArray
     * @param bool $isOffline
     * @return array
     */
    public function getOnlineShippingQuotes($urlsArray, $isOffline = false)
    {
        $deliveryMessage = (string)$this->scopeConfig->getValue(
            "carriers/Iget_Correios/deliverydays_message",
            $this->storeScope
        );
        if ($deliveryMessage === "") {
            $deliveryMessage = "%s - Em média %d dia(s)";
        }
        $showDeliveryDays = (bool)$this->scopeConfig->getValue(
            "carriers/Iget_Correios/show_deliverydays",
            $this->storeScope
        );
        $addDeliveryDays = (int)$this->scopeConfig->getValue(
            "carriers/Iget_Correios/add_deliverydays",
            $this->storeScope
        );
        $handlingFee = 0;
        if ($this->scopeConfig->getValue(
            "carriers/Iget_Correios/handling_fee",
            $this->storeScope
        ) != "") {
            if (is_numeric($this->scopeConfig->getValue(
                "carriers/Iget_Correios/handling_fee",
                $this->storeScope
            ))) {
                $handlingFee = $this->scopeConfig->getValue(
                    "carriers/Iget_Correios/handling_fee",
                    $this->storeScope
                );
            }
        }
        if ((bool)$isOffline === true) {
            $addDeliveryDays = 0;
        }
        $ratingsCollection = array();
        foreach ($urlsArray as $url_d) {
            $xml=null;
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url_d);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                ob_start();
                curl_exec($ch);
                curl_close($ch);
                $content = ob_get_contents();
                ob_end_clean();

                if ($content) {
                    $xml = new \SimpleXMLElement($content);
                }
            } catch (\Exception $e) {
                $this->logMessage("Error in consult XML: ".$e->getMessage());
                continue;
            }
            if ($xml!=null) {
                foreach ($xml->cServico as $servico) {
                    if ((float)$servico->Valor > 0) {
                        try {
                            $data = array();
                            if (!$showDeliveryDays) {
                                $data['servico'] = $this->getMethodName($servico->Codigo);
                            } else {
                                $data['servico'] = sprintf(
                                    $deliveryMessage,
                                    $this->getMethodName($servico->Codigo),
                                    intval($servico->PrazoEntrega + $addDeliveryDays)
                                );
                            }
                            $data['valor'] = str_replace(",", ".", $servico->Valor) + $handlingFee;
                            $data['prazo'] = $servico->PrazoEntrega + $addDeliveryDays;
                            $data['servico_codigo'] = $servico->Codigo;
                            array_push($ratingsCollection, $data);
                            if ($servico->MsgErro != "") {
                                $this->logMessage("Error on helper line165: " . $servico->MsgErro);
                            }
                        } catch (\Exception $ex) {
                            $this->logMessage("Error in consult XML2: ".$ex->getMessage());
                        }
                    } else {
                        $this->logMessage("Error in consult XML3: Value is zero. The service is not availble to this shipping");
                    }
                }
            }
        }
        return $ratingsCollection;
    }

    /**
     * @param $request
     * @param $fromCountryId
     * @return bool
     */
    public function checkCountry($request, $fromCountryId)
    {
        $from = (string)$fromCountryId;
        $to = (string)$request->getDestCountryId();
        if ($from !== "BR" || $to !== "BR") {
            return false;
        }
        return true;
    }

    /**
     * @param $zipcode
     * @return bool|null|string|string[]
     */
    public function formatZip($zipcode)
    {
        $new = trim($zipcode);
        $new = preg_replace('/[^0-9\s]/', '', $new);
        if (!preg_match("/^[0-9]{7,8}$/", $new)) {
            return false;
        } elseif (preg_match("/^[0-9]{7}$/", $new)) {
            $new = "0" . $new;
        }
        return $new;
    }

    /**
     * @param $service
     * @param $firstPostcode
     * @param $lastPostcode
     * @return bool
     */
    public function canCreateOfflineTrack($service, $firstPostcode, $lastPostcode)
    {
        $collection = $this->cotacoesFactory->create()
            ->getCollection()
            ->addFieldToFilter('cep_inicio', ["lteq" => $firstPostcode])
            ->addFieldToFilter('cep_fim', ["gteq" => $lastPostcode])
            ->addFilter("servico", $service);
        if ($collection->count() > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $originalPrice
     * @return mixed
     */
    public function formatPrice($originalPrice)
    {
        $finalPrice = str_replace(" ", "", $originalPrice);
        $finalPrice = str_replace("R$", "", $finalPrice);
        return $finalPrice;
    }

    /**
     * @param $weight
     * @return string
     */
    public function fixWeight($weight)
    {
        $result = $weight;
        if (((string)$this->scopeConfig->getValue(
            "carriers/Iget_Correios/weight_type",
            $this->storeScope
        ) === 'gr')) {
            $result = number_format($weight/1000, 2, '.', '');
        }
        return $result;
    }

    /**
     * @param $request
     * @return bool
     */
    public function checkWeightRange($request)
    {
        $weight = $this->fixWeight($request->getPackageWeight());

        if ($weight <= 0) {
            return false;
        }

        $maxWeight = $this->scopeConfig->getValue(
            "carriers/Iget_Correios/max_weight",
            $this->storeScope
        );

        return !$maxWeight || $weight < (double) $maxWeight;
    }

    /**
     * @return mixed
     */
    private function getWeightUnit()
    {
        return $this->scopeConfig->getValue(
            'general/locale/weight_unit',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Convert given weight (in store unit) to kilogram
     * @param $weight
     * @return float
     */
    public function getWeightInKg($weight)
    {
        if ($this->weightUnit == 'lbs') {
            return $weight * 0.453592;
        }

        return $weight;
    }

    /**
     * Get available boxes from config and sort it by it volume
     * @return array|mixed
     */
    private function getAvailableBoxes()
    {
        $availableBoxes = json_decode($this->getConfig('packages/available_boxes'), true);

        $availableBoxes = array_map(function($box, $id) {
            $box['id'] = $id;
            $box['cubic'] = ceil(($box['height'] * $box['width'] * $box['depth']));
            $box['used'] = [];
            return $box;
        }, $availableBoxes, array_keys($availableBoxes));

        usort($availableBoxes, function($a, $b) {
            if ($a['cubic'] == $b['cubic']) {
                return 0;
            }
            return $a['cubic'] - $b['cubic'];
        });

        return $availableBoxes;
    }

    public function getPackages($quote)
    {
        $availableBoxes = $this->getAvailableBoxes();
        $availableItems = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $productItem = $item->getProduct();
            $product = $this->productRepository->getById($productItem->getId());

            $boxes =  explode(',', $product->getData('correios_boxes'));

            $smallestCubic = null;
            $smallestBox = null;

            foreach ($availableBoxes as $box) {
                // Since our available boxes is sorted by cubic, we need to get the
                // first availableBox that this product fits.
                if (in_array($box['id'], $boxes)) {
                    $smallestCubic = $box['cubic'];
                    $smallestBox = $box;
                    continue;
                }
            }

            $width = floatval($product->getData('correios_width'));
            $height = floatval($product->getData('correios_height'));
            $depth = floatval($product->getData('correios_depth'));
            $weight = $this->getWeightInKg(floatval($product->getData('weight')));

            $availableItems[] = [
                'product' => $product,
                'height' => $width,
                'width' => $height,
                'weight' => $weight,
                'depth' => $depth,
                'price' => $item->getPrice(),
                'qty' => $item->getQty(),
                'cubic' => ceil(($width * $height * $depth) * $item->getQty()),
                'boxes' => $boxes,
                'smallest_box' => $smallestBox,
                'smallest_cubic' => $smallestCubic,
            ];
        }

        usort($availableItems, function($a, $b) {
            if ($a['smallest_cubic'] == $b['smallest_cubic']) {
                return 0;
            }
            return $b['smallest_cubic'] - $a['smallest_cubic'];
        });

        // Fill the smallest full boxes a product can fit.
        // eg. If the 2 boxes fits 12 products, and the product
        // have qty equals to 24, it will search for the smallest box
        // and use 2 boxes with 12 items on each box.
        foreach ($availableItems as $_productId => $item) {
            foreach ($availableBoxes as $boxIndex => $box) {
                if (in_array($box['id'], $item['boxes'])) {
                    // Get quantity of full boxes this item can fill
                    while (intval($item['qty'] / $box['capacity'])) {
                        $box['used'][] = [
                            'qty' => $box['capacity'],
                            'weight' => $item['weight'] * $box['capacity'],
                            'value' => $item['price'] * $box['capacity'],
                        ];
                        $item['qty'] -= $box['capacity'];
                    }
                    $availableBoxes[$boxIndex] = $box;
                    break;
                }
            }
            $availableItems[$_productId] = $item;
        }

        // Fit remaining products in the smallest box possible
        do {
            // Find the box that fit maximum of products
            $maxPendingCount = 0;
            $maxPendingBoxIndex = null;
            foreach ($availableBoxes as $boxIndex => $box) {
                $pending = 0;
                foreach ($availableItems as $_productId => $item) {
                    if (in_array($box['id'], $item['boxes'])) {
                        $pending += $item['qty'];
                    }
                }
                if ($pending > $maxPendingCount) {
                    $maxPendingCount = $pending;
                    $maxPendingBoxIndex = $boxIndex;
                }
            }
            // Fit all possible products on that box
            if ($maxPendingCount > 0) {
                $pendingToAdd = 0;
                $weight = 0;
                $value = 0;
                foreach ($availableItems as $_productId => $item) {
                    // If the item fits on the maxPendingBox
                    if (in_array($availableBoxes[$maxPendingBoxIndex]['id'], $item['boxes'])) {
                        $qty = min($availableBoxes[$maxPendingBoxIndex]['capacity'] - $pendingToAdd, $item['qty']);
                        $pendingToAdd += $qty;
                        $weight += $qty * $item['weight'];
                        $value += $qty * $item['price'];
                        $item['qty'] -= $qty;
                    }
                    $availableItems[$_productId] = $item;
                }
                $availableBoxes[$maxPendingBoxIndex]['used'][] = [
                    'qty' => $pendingToAdd,
                    'weight' => $weight,
                    'value' => $value,
                ];
            }
        } while ($maxPendingCount !== 0);

        $packages = [];

        foreach($availableBoxes as $box) {
            foreach ($box['used'] as $usedBox) {
                $packages[] = [
                    'width' => $box['width'],
                    'height' => $box['height'],
                    'depth' => $box['depth'],
                    'weight' => $usedBox['weight'],
                    'value' => $usedBox['value'],
                ];
            }
        }

        // Add individual package for each item that doesn't fit on a default box
        foreach($availableItems as $item) {
            for($i = $item['qty']; $i > 0; $i--) {
                $packages[] = [
                    'width' => $item['width'],
                    'height' => $item['height'],
                    'depth' => $item['depth'],
                    'weight' => $item['weight'],
                    'value' => $item['value'],
                ];
            }
        }

        return $packages;
    }

    /**
     * @param $quote
     * @return int|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCubicWeight($quote)
    {
        $cubicWeight = 0;
        $items = $quote->getAllVisibleItems();
        $maxH = 90;
        $minH = 2;
        $maxW = 90;
        $minW = 16;
        $maxD = 90;
        $minD = 11;
        $sumMax = 160;
        $coefficient = 6000;
        $validate = (bool)$this->scopeConfig->getValue(
            'carriers/Iget_Correios/validate_dimensions',
            $this->storeScope
        );
        foreach ($items as $item) {
            $productItem = $item->getProduct();
            $product = $this->productRepository->getById($productItem->getId());
            $width = ((int)$product->getData('correios_width') === 0) ? (int)$this->scopeConfig->getValue(
                "carriers/Iget_Correios/default_width",
                $this->storeScope
            ) : (int)$product->getData('correios_width');
            $height = ((int)$product->getData('correios_height') === 0) ? (int)$this->scopeConfig->getValue(
                "carriers/Iget_Correios/default_height",
                $this->storeScope
            ) : (int)$product->getData('correios_height');
            $depth = ((int)$product->getData('correios_depth') === 0) ? (int)$this->scopeConfig->getValue(
                "carriers/Iget_Correios/default_depth",
                $this->storeScope
            ) : (int)$product->getData('correios_depth');

            if ($validate && ($height > $maxH || $height < $minH || $depth > $maxD ||
                    $depth < $minD || $width > $maxW || $width < $minW ||
                    ($height+$depth+$width) > $sumMax)) {
                $this->logMessage("Invalid Product Dimensions");
                return 0;
            }
            $cubicWeight += (($width * $depth * $height) / $coefficient) * $item->getQty();
        }
        return $this->fixWeight($cubicWeight);
    }

    /**
     * @param $message
     */
    public function logMessage($message)
    {
        if ((bool)($this->scopeConfig->getValue(
            "carriers/Iget_Correios/enabled_log",
            $this->storeScope
        ))) {
            $this->logger->info($message);
        }
    }

    /**
     * @return bool
     */
    public function updateOfflineTracks()
    {
        $lastItem = $this->cotacoesFactory->create()
            ->getCollection()
            ->addFieldToFilter("valor", array('gt' => 0))
            ->setOrder("ultimo_update", "desc");
        if ($lastItem->count() > 0) {
            $lastItem = $lastItem->getFirstItem();
            $this->logMessage("Last Update: " . $lastItem->getUltimoUpdate());
            $lastUpdateDatetime = $lastItem->getUltimoUpdate();
        } else {
            $lastUpdateDatetime = null;
        }
        $daysUpdate = $this->scopeConfig->getValue(
            "carriers/Iget_Correios/maxdays_update",
            $this->storeScope
        );
        if (!is_numeric($daysUpdate)) {
            $daysUpdate = 15;
        }
        if ($daysUpdate <= 0) {
            $daysUpdate = 15;
        }
        if ($lastUpdateDatetime !== null) {
            $nowDate = date('Y-m-d H:i:s');
            $diff = abs(strtotime($nowDate) - strtotime($lastUpdateDatetime));
            $years = floor($diff / (365 * 60 * 60 * 24));
            $months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
            $days = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
            $this->logMessage("Days: " . $days . " daysUpdate: " . $daysUpdate);
            if ($days < $daysUpdate) {
                return false;
            }
        }
        $collectionToUpdate = $this->cotacoesFactory->create()->getCollection();
        $this->updateTrackCollection($collectionToUpdate);
        $this->logMessage("Offline Postcode Tracks updated");
    }

    /**
     * @param (int)$methods
     * @return array
     */
    public function getPostMethodCodes()
    {
        $methods = explode(',', $this->getConfig('post_methods/enabled_methods'));
        $arrayMethods = array();

        foreach ($methods as $method) {
            $method = (int) $method;
            if ($method === 4162 || $method === 40010) {
                if ($this->getConfig('custom_post_methods/sedex') != "") {
                    $arrayMethods[] = (int) $this->getConfig('custom_post_methods/sedex');
                } else {
                    $arrayMethods[] = $method;
                }
            } elseif ($method===41106 || $method===4669) {
                if ($this->getConfig('custom_post_methods/pac') != "") {
                    $arrayMethods[] = (int) $this->getConfig('custom_post_methods/pac');
                } else {
                    $arrayMethods[] = $method;
                }
            } elseif ($method===40215) {
                if ($this->getConfig('custom_post_methods/sedex_10') != "") {
                    $arrayMethods[] = (int) $this->getConfig('custom_post_methods/sedex_10');
                } else {
                    $arrayMethods[] = $method;
                }
            } elseif ($method===40290) {
                if ($this->getConfig('custom_post_methods/sedex_hoje') != "") {
                    $arrayMethods[] = (int) $this->getConfig('custom_post_methods/sedex_hoje');
                } else {
                    $arrayMethods[] = $method;
                }
            } elseif ($method===40045) {
                if ($this->getConfig('custom_post_methods/sedex_cobrar') != "") {
                    $arrayMethods[] = (int) $this->getConfig('custom_post_methods/sedex_cobrar');
                } else {
                    $arrayMethods[] = $method;
                }
            }
        }
        return $arrayMethods;
    }

    /**
     * Truncate collection data table
     * @throws \Exception
     */
    public function truncateCotacoes()
    {
        /**
         * @var $collection \Iget\Correios\Model\ResourceModel\Cotacoes\Collection
         */
        $collection = $this->cotacoesFactory->create()->getCollection();
        foreach ($collection as $item) {
            $cotacao = $this->cotacoesFactory->create()->load($item->getId());
            $this->resourceModel->delete($cotacao);
        }
    }

    /**
     * Make curl call returning the result
     * @param string $url
     * @return mixed|null
     */
    public function makeCurlCall($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        } catch (\Exception $ex) {
            $this->logMessage("Error making a curl call: ".$ex->getMessage());
            return null;
        }
    }

    /**
     * Return all codes used by PAC
     * @return array
     */
    public function getPacCodes()
    {
        $pac = array(41106, 4669);
        $customPAC = (string)$this->scopeConfig->getValue(
            'correios_postingmethods_config/settings/pac',
            $this->storeScope
        );
        if ($customPAC !== null && $customPAC !== "") {
            $pac[] = $customPAC;
        }
        return $pac;
    }

    /**
     * Merge all packages into a single package by volume
     * @param array $packages
     * @return array
     */
    public function mergePackages(array $packages)
    {
        if (count($packages) <= 1) {
            return $packages;
        }

        $volume = 0;
        $weight = 0;
        $value = 0;

        foreach ($packages as $package) {
            $volume += $package['width'] * $package['height'] * $package['depth'];
            $weight += $package['weight'];
            $value += $package['value'];
        }

        $side = round(pow($volume, 1/3));

        return [
            [
                'width' => $side,
                'height' => $side,
                'depth' => $side,
                'weight' => $weight,
                'value' => $value,
            ]
        ];
    }
}
