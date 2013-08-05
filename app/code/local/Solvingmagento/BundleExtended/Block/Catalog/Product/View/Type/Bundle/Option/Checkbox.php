<?php
/**
 * Solvingmagento_BundleExtended checkbox option class override
 *
 * PHP version 5.3
 *
 * @category  Solvingmagento
 * @package   Solvingmagento_BundleExtended
 * @author    Oleg Ishenko <oleg.ishenko@solvingmagento.com>
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version   GIT: <0.1.0>
 * @link      http://www.solvingmagento.com/
 *
 */

/** Solvingmagento_BundleExtended_Block_Catalog_Product_View_Type_Bundle_Option_Checkbox
 *
 * @category Solvingmagento
 * @package  Solvingmagento_BundleExtended
 *
 * @author   Oleg Ishenko <oleg.ishenko@solvingmagento.com>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version  Release: <package_version>
 * @link     http://www.solvingmagento.com/
 */

class Solvingmagento_BundleExtended_Block_Catalog_Product_View_Type_Bundle_Option_Checkbox
    extends Mage_Bundle_Block_Catalog_Product_View_Type_Bundle_Option_Checkbox
{
    /**
     * Sets a custom template
     *
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('bundleextended/catalog/product/view/type/bundle/option/checkbox.phtml');
    }
    
    /**
     * Combines default quantity properties of an option's checkbox selections
     * into an array
     * 
     * @return array
     */
    public function getSelectionValues()
    {
     
        $option    = $this->getOption();
        $selections = $option->getSelections();
        
        $result = array();
        
        foreach ($selections as $selection) {
            $result[$selection->getSelectionId()] = array(
                'default_qty'  => max($selection->getSelectionQty() * 1, 1),
                'user_defined' => (bool) $selection->getSelectionCanChangeQty()
            );
        }
        
        return $result;
    }
}