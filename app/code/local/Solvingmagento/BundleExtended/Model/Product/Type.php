<?php
/**
 * Solvingmagento_BundleExtended product type class override
 *
 * PHP version 5.3
 *
 * @category  Solvingmagento
 * @package   Solvingmagento_BundleExtended
 * @author    Magento Core Team <core@magentocommerce.com>
 * @author    Oleg Ishenko <oleg.ishenko@solvingmagento.com>
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version   GIT: <0.1.0>
 * @link      http://www.solvingmagento.com/
 *
 */

/** Solvingmagento_BundleExtended_Model_Product_Type
 *
 * @category Solvingmagento
 * @package  Solvingmagento_BundleExtended
 *
 * @author   Magento Core Team <core@magentocommerce.com>
 * @author   Oleg Ishenko <oleg.ishenko@solvingmagento.com>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version  Release: <package_version>
 * @link     http://www.solvingmagento.com/
 */

class Solvingmagento_BundleExtended_Model_Product_Type 
    extends Mage_Bundle_Model_Product_Type
{
    /**
     * Prepare product and its configuration to be added to some products list.
     * Perform standard preparation process and then prepare of bundle selections options.
     *
     * @param Varien_Object $buyRequest
     * @param Mage_Catalog_Model_Product $product
     * @param string $processMode
     * @return array|string
     */
    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode)
    {
        $result = $this->_abstractPrepareProduct($buyRequest, $product, $processMode);

        if (is_string($result)) {
            return $result;
        }

        $selections = array();
        $product = $this->getProduct($product);
        $isStrictProcessMode = $this->_isStrictProcessMode($processMode);

        $skipSaleableCheck = Mage::helper('catalog/product')
            ->getSkipSaleableCheck();
        $_appendAllSelections = 
            (bool)$product->getSkipCheckRequiredOption() || $skipSaleableCheck;

        $options = $buyRequest->getBundleOption();
        if (is_array($options)) {
            $options = array_filter($options, 'intval');
            $qtys = $buyRequest->getBundleOptionQty();
            foreach ($options as $_optionId => $_selections) {
                if (empty($_selections)) {
                    unset($options[$_optionId]);
                }
            }
            $optionIds = array_keys($options);

            if (empty($optionIds) && $isStrictProcessMode) {
                return Mage::helper('bundle')
                        ->__('Please select options for product.');
            }

            $product
                ->getTypeInstance(true)
                ->setStoreFilter($product->getStoreId(), $product);
            
            $optionsCollection = $this->getOptionsCollection($product);
            
            if (!$this->getProduct($product)->getSkipCheckRequiredOption() 
                && $isStrictProcessMode
            ) {
                foreach ($optionsCollection->getItems() as $option) {
                    if ($option->getRequired() 
                        && !isset($options[$option->getId()])
                    ) {
                        return Mage::helper('bundle')
                            ->__('Required options are not selected.');
                    }
                }
            }
            $selectionIds = array();

            foreach ($options as $optionId => $selectionId) {
                if (!is_array($selectionId)) {
                    if ($selectionId != '') {
                        $selectionIds[] = (int)$selectionId;
                    }
                } else {
                    foreach ($selectionId as $id) {
                        if ($id != '') {
                            $selectionIds[] = (int)$id;
                        }
                    }
                }
            }
            // If product has not been configured yet 
            // then $selections array should be empty
            if (!empty($selectionIds)) {
                $selections = $this->getSelectionsByIds($selectionIds, $product);

                // Check if added selections are still on sale
                foreach ($selections->getItems() as $key => $selection) {
                    if (!$selection->isSalable() && !$skipSaleableCheck) {
                        $_option = $optionsCollection
                            ->getItemById($selection->getOptionId());
                        
                        if (is_array($options[$_option->getId()]) 
                            && count($options[$_option->getId()]) > 1
                        ) {
                            $moreSelections = true;
                        } else {
                            $moreSelections = false;
                        }
                        if ($_option->getRequired()
                            && (!$_option->isMultiSelection() 
                                || ($_option->isMultiSelection() && !$moreSelections))
                        ) {
                            return Mage::helper('bundle')
                                ->__('Selected required options are not available.');
                        }
                    }
                }

                $optionsCollection->appendSelections(
                    $selections, 
                    false, 
                    $_appendAllSelections
                );

                $selections = $selections->getItems();
            } else {
                $selections = array();
            }
        } else {
            $product->setOptionsValidationFail(true);
            $product
                ->getTypeInstance(true)
                ->setStoreFilter($product->getStoreId(), $product);

            $optionCollection = $product
                ->getTypeInstance(true)
                ->getOptionsCollection($product);

            $optionIds = $product->getTypeInstance(true)->getOptionsIds($product);
            $selectionIds = array();

            $selectionCollection = $product->getTypeInstance(true)
                ->getSelectionsCollection(
                    $optionIds,
                    $product
                );

            $options = $optionCollection->appendSelections(
                $selectionCollection,
                false, 
                $_appendAllSelections
            );

            foreach ($options as $option) {
                if ($option->getRequired() 
                    && count($option->getSelections()) == 1
                ) {
                    $selections = array_merge(
                        $selections, 
                        $option->getSelections()
                    );
                } else {
                    $selections = array();
                    break;
                }
            }
        }
        if (count($selections) > 0 || !$isStrictProcessMode) {
            $uniqueKey = array($product->getId());
            $selectionIds = array();

            // Shuffle selection array by option position
            usort($selections, array($this, 'shakeSelections'));

            foreach ($selections as $selection) {
                $opId   = $selection->getOptionId();
                $combId = $opId . '-' . $selection->getSelectionId();
                if ($selection->getSelectionCanChangeQty() 
                    && (isset($qtys[$opId]) || isset($qtys[$combId]))
                ) {
                    if ((float) $qtys[$opId] > 0) {
                        $qty = $qtys[$opId];
                    } elseif ((float) $qtys[$combId] > 0) {
                        $qty = $qtys[$combId];
                    } else {
                        $qty = 1;
                    }
                } else {
                    $qty = (float) $selection->getSelectionQty() ? $selection->getSelectionQty() : 1;
                }
                $qty = (float) $qty;

                $product->addCustomOption(
                    'selection_qty_' . $selection->getSelectionId(), 
                    $qty, 
                    $selection
                );
                $selection->addCustomOption(
                    'selection_id', 
                    $selection->getSelectionId()
                );

                $beforeQty = 0;
                $customOption = $product
                    ->getCustomOption('product_qty_' . $selection->getId());
                
                if ($customOption) {
                    $beforeQty = (float)$customOption->getValue();
                }
                $product->addCustomOption(
                    'product_qty_' . $selection->getId(),
                    $qty + $beforeQty, 
                    $selection
                );

                /*
                 * Create extra attributes that will be converted to
                 * product options in order item
                 * for selection (not for all bundle)
                 */
                $price = $product
                    ->getPriceModel()
                    ->getSelectionFinalTotalPrice($product, $selection, 0, $qty);
                $attributes = array(
                    'price'        => Mage::app()->getStore()->convertPrice($price),
                    'qty'          => $qty,
                    'option_label' => $selection->getOption()->getTitle(),
                    'option_id'    => $selection->getOption()->getId()
                );

                $_result = $selection
                    ->getTypeInstance(true)
                    ->prepareForCart($buyRequest, $selection);
                
                if (is_string($_result) && !is_array($_result)) {
                    return $_result;
                }

                if (!isset($_result[0])) {
                    return Mage::helper('checkout')
                        ->__('Cannot add item to the shopping cart.');
                }

                $result[] = $_result[0]->setParentProductId($product->getId())
                    ->addCustomOption(
                        'bundle_option_ids',
                        serialize(array_map('intval', $optionIds))
                    )
                    ->addCustomOption(
                        'bundle_selection_attributes', 
                        serialize($attributes)
                    );

                if ($isStrictProcessMode) {
                    $_result[0]->setCartQty($qty);
                }

                $selectionIds[] = $_result[0]->getSelectionId();
                $uniqueKey[] = $_result[0]->getSelectionId();
                $uniqueKey[] = $qty;
            }

            // "unique" key for bundle selection and add it to selections 
            // and bundle for selections
            $uniqueKey = implode('_', $uniqueKey);
            foreach ($result as $item) {
                $item->addCustomOption('bundle_identity', $uniqueKey);
            }
            $product->addCustomOption(
                'bundle_option_ids',
                serialize(array_map('intval', $optionIds))
            );
            $product->addCustomOption(
                'bundle_selection_ids', 
                serialize($selectionIds)
            );

            return $result;
        }

        return $this->getSpecifyOptionMessage();
    }
    
    
    /**
     * Originally Mage_Catalog_Model_Product_Type_Abstract::_prepareProduct, 
     * copied here to enable calling it directly, without landing in
     * Mage_Bundle_Model_Product_Type::_prepareProduct first.
     * 
     * Prepare product and its configuration to be added to some products list.
     * Perform standard preparation process and then prepare options belonging
     *  to specific product type.
     *
     * @param  Varien_Object $buyRequest
     * @param  Mage_Catalog_Model_Product $product
     * @param  string $processMode
     * @return array|string
     */
    protected function _abstractPrepareProduct(
            Varien_Object $buyRequest, 
            $product, 
            $processMode
    ) {
        $product = $this->getProduct($product);
        /* @var Mage_Catalog_Model_Product $product */
        // try to add custom options
        try {
            $options = $this->_prepareOptions(
                $buyRequest, 
                $product, 
                $processMode
            );
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        }

        if (is_string($options)) {
            return $options;
        }
        // try to found super product configuration
        // (if product was buying within grouped product)
        $superProductConfig = $buyRequest->getSuperProductConfig();
        if (!empty($superProductConfig['product_id'])
            && !empty($superProductConfig['product_type'])
        ) {
            $superProductId = (int) $superProductConfig['product_id'];
            if ($superProductId) {
                if (!$superProduct = 
                        Mage::registry('used_super_product_'.$superProductId)) {
                    $superProduct = Mage::getModel('catalog/product')
                            ->load($superProductId);
                    
                    Mage::register(
                        'used_super_product_'.$superProductId,
                        $superProduct
                    );
                }
                if ($superProduct->getId()) {
                    $assocProductIds = $superProduct
                            ->getTypeInstance(true)
                            ->getAssociatedProductIds($superProduct);
                    
                    if (in_array($product->getId(), $assocProductIds)) {
                        $productType = $superProductConfig['product_type'];
                        $product->addCustomOption(
                            'product_type',
                            $productType,
                            $superProduct
                        );

                        $buyRequest->setData('super_product_config', array(
                            'product_type' => $productType,
                            'product_id'   => $superProduct->getId()
                        ));
                    }
                }
            }
        }

        $product->prepareCustomOptions();
        $buyRequest->unsetData('_processing_params'); // One-time params only
        $product->addCustomOption(
            'info_buyRequest', 
            serialize($buyRequest->getData())
        );

        if ($options) {
            $optionIds = array_keys($options);
            $product->addCustomOption('option_ids', implode(',', $optionIds));
            foreach ($options as $optionId => $optionValue) {
                $product->addCustomOption(
                    self::OPTION_PREFIX . $optionId, 
                    $optionValue
                );
            }
        }

        // set quantity in cart
        if ($this->_isStrictProcessMode($processMode)) {
            $product->setCartQty($buyRequest->getQty());
        }
        $product->setQty($buyRequest->getQty());

        return array($product);
    }
}