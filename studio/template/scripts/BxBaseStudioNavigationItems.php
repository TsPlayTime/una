<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    UnaView UNA Studio Representation classes
 * @ingroup     UnaStudio
 * @{
 */

class BxBaseStudioNavigationItems extends BxDolStudioNavigationItems
{
    protected $sUrlPage;
    protected $sUrlViewItems;

    function __construct($aOptions, $oTemplate = false)
    {
        parent::__construct($aOptions, $oTemplate);

        $this->_aOptions['actions_single']['edit']['attr']['title'] = _t('_adm_nav_btn_items_edit');
        $this->_aOptions['actions_single']['delete']['attr']['title'] = _t('_adm_nav_btn_items_delete');

        $this->sUrlPage = BX_DOL_URL_STUDIO . 'builder_menu.php?page=items';
        $this->sUrlViewItems = $this->sUrlPage . '&module=%s&set=%s';
    }

    public function performActionImport()
    {
        $oGrid = BxDolGrid::getObjectInstance('sys_studio_nav_import');
        if(!$oGrid)
            return '';

        $sContent = BxTemplFunctions::getInstance()->popupBox('adm-nav-item-import-popup', _t('_adm_nav_txt_items_import_popup'), $this->_oTemplate->parseHtmlByName('nav_import_item.html', array(
            'grid' => $oGrid->getCode()
        )));

        echoJson(array('popup' => array('html' => $sContent, 'options' => array('closeOnOuterClick' => false))));
    }

    public function performActionAdd()
    {
        $sAction = 'add';

        $oForm = $this->_getFormObject($sAction);
        $oForm->initChecker();

        if($oForm->isSubmittedAndValid()) {
            if(!empty($_FILES['icon_image']['tmp_name'])) {
                $oStorage = BxDolStorage::getObjectInstance(BX_DOL_STORAGE_OBJ_IMAGES);

                $mixedIcon = $oStorage->storeFileFromForm($_FILES['icon_image'], false, 0);
                if($mixedIcon === false) {
                    echoJson(array('msg' => _t('_adm_nav_err_items_icon_image') . $oStorage->getErrorString()));
                    return;
                }

                $oStorage->afterUploadCleanup($mixedIcon, 0);
                BxDolForm::setSubmittedValue('icon', $mixedIcon, $oForm->aFormAttrs['method']);
            }

            $oPermalinks = BxDolPermalinks::getInstance();

            $sLink = $oForm->getCleanValue('link');
            $sLink = $oPermalinks->unpermalink($sLink);
            BxDolForm::setSubmittedValue('link', $sLink, $oForm->aFormAttrs['method']);

            $sName = $oPermalinks->getPageNameFromLink($sLink);
            if($sName == '') {
                $sLanguage = BxDolStudioLanguagesUtils::getInstance()->getCurrentLangName(false);

                $sName = BxDolForm::getSubmittedValue('title-' . $sLanguage, $oForm->aFormAttrs['method']);
                if(empty($sName))
                    $sName = BxDolForm::getSubmittedValue('title_system-' . $sLanguage, $oForm->aFormAttrs['method']);

                $sName = uriGenerate($sName, 'sys_menu_items', 'name', 'item');
            }

            bx_import('BxDolStudioUtils');
            $iId = (int)$oForm->insert(array('module' => BX_DOL_STUDIO_MODULE_CUSTOM, 'name' => $sName, 'active' => 1, 'order' => $this->oDb->getItemOrderMax($this->sSet) + 1));
            if($iId != 0)
                $aRes = array('grid' => $this->getCode(false), 'blink' => $iId);
            else
                $aRes = array('msg' => _t('_adm_nav_err_items_create'));

            echoJson($aRes);
        }
        else {
            $sContent = BxTemplStudioFunctions::getInstance()->popupBox('adm-nav-item-create-popup', _t('_adm_nav_txt_items_create_popup'), $this->_oTemplate->parseHtmlByName('nav_add_item.html', array(
                'form_id' => $oForm->aFormAttrs['id'],
                'form' => $oForm->getCode(true),
                'object' => $this->_sObject,
                'action' => $sAction
            )));

            echoJson(array('popup' => array('html' => $sContent, 'options' => array('closeOnOuterClick' => false))));
        }
    }

    public function performActionEdit($bUpdateGrid = false)
    {
        $sAction = 'edit';

        $aItem = $this->_getItem('getItems');
        if($aItem === false) {
            echoJson(array());
            exit;
        }

        $oForm = $this->_getFormObject($sAction, $aItem);
        $oForm->initChecker();

        if($oForm->isSubmittedAndValid()) {
            $bIconImageCur = is_numeric($aItem['icon']) && (int)$aItem['icon'] != 0;
            $bIconImageNew = !empty($_FILES['icon_image']['tmp_name']);

            $sIconFont = $oForm->getCleanValue('icon');
            $bIconFont = !empty($sIconFont);

            if($bIconImageCur && ($bIconImageNew || $bIconFont)) {
                $oStorage = BxDolStorage::getObjectInstance(BX_DOL_STORAGE_OBJ_IMAGES);
                if(!$oStorage->deleteFile((int)$aItem['icon'], 0)) {
                    echoJson(array('msg' => _t('_adm_nav_err_items_icon_image_remove')));
                    return;
                }
            }

            $sIcon = $sIconFont;
            if($bIconImageNew) {
                $oStorage = BxDolStorage::getObjectInstance(BX_DOL_STORAGE_OBJ_IMAGES);
                $sIcon = $oStorage->storeFileFromForm($_FILES['icon_image'], false, 0);
                if($sIcon === false) {
                    echoJson(array('msg' => _t('_adm_nav_err_items_icon_image') . $oStorage->getErrorString()));
                    return;
                }

                $oStorage->afterUploadCleanup($sIcon, 0);
            } else if($bIconImageCur && !$bIconFont)
                $sIcon = $aItem['icon'];

            BxDolForm::setSubmittedValue('icon', $sIcon, $oForm->aFormAttrs['method']);

            $sLink = $oForm->getCleanValue('link');
            $sLink = BxDolPermalinks::getInstance()->unpermalink($sLink);
            BxDolForm::setSubmittedValue('link', $sLink, $oForm->aFormAttrs['method']);

            $sSubmenu = $oForm->getCleanValue('submenu_object');
            if(empty($sSubmenu))
            	BxDolForm::setSubmittedValue('submenu_popup', 0, $oForm->aFormAttrs['method']);

            $sTarget = $oForm->getCleanValue('target');
            if($sTarget === false && !in_array($aItem['target'], array('', '_blank')))
                unset($oForm->aInputs['target']);

            if($oForm->update($aItem['id']) !== false)
                $aRes = array('grid' => $this->getCode(false), 'blink' => $aItem['id']);
            else
                $aRes = array('msg' => _t('_adm_nav_err_items_edit'));

            echoJson($aRes);
        }
        else {
            $sTitle = _t($aItem['title']);
            $sContent = BxTemplStudioFunctions::getInstance()->popupBox('adm-nav-item-edit-popup', _t('_adm_nav_txt_items_edit_popup', ($sTitle != "" ? '"' . $sTitle . '"' : '')), $this->_oTemplate->parseHtmlByName('nav_add_item.html', array(
                'form_id' => $oForm->aFormAttrs['id'],
                'form' => $oForm->getCode(true),
                'object' => $this->_sObject,
                'action' => $sAction
            )));

            $aRes = array('popup' => array('html' => $sContent, 'options' => array('closeOnOuterClick' => false)));
            if($bUpdateGrid)
                $aRes = array_merge($aRes, array('grid' => $this->getCode(false), 'blink' => $aItem['id']));

            echoJson($aRes);
        }
    }

    public function performActionDelete()
    {
        $iAffected = 0;
        $aIds = bx_get('ids');
        if(!$aIds || !is_array($aIds)) {
            echoJson(array());
            exit;
        }

        $aIdsAffected = array ();
        foreach($aIds as $iId) {
            if(!$this->deleteById($iId))
                continue;

            $aIdsAffected[] = $iId;
            $iAffected++;
        }

        echoJson($iAffected ? array('grid' => $this->getCode(false), 'blink' => $aIdsAffected) : array('msg' => _t('_adm_nav_err_items_delete')));
    }

    public function performActionShowTo()
    {
        $sAction = 'show_to';

        $aItem = $this->_getItem('getItems');
        if($aItem === false) {
            echoJson(array());
            exit;
        }

		bx_import('BxDolStudioUtils');
        $aForm = array(
            'form_attrs' => array(
                'id' => 'adm-nav-item-create',
                'action' => BX_DOL_URL_ROOT . 'grid.php?o=' . $this->_sObject . '&a=' . $sAction . '&set=' . $this->sSet,
                'method' => 'post'
            ),
            'params' => array (
                'db' => array(
                    'table' => 'sys_menu_items',
                    'key' => 'id',
                    'uri' => '',
                    'uri_title' => '',
                    'submit_name' => 'do_submit'
                ),
            ),
            'inputs' => array (
                'id' => array(
                    'type' => 'hidden',
                    'name' => 'id',
                    'value' => $aItem['id'],
                    'db' => array (
                        'pass' => 'Int',
                    ),
                ),
                'visible_for' => array(
                    'type' => 'select',
                    'name' => 'visible_for',
                    'caption' => _t('_adm_nav_txt_items_visible_for'),
                    'info' => '',
                    'value' => $aItem['visible_for_levels'] == BX_DOL_INT_MAX ? BX_DOL_STUDIO_VISIBLE_ALL : BX_DOL_STUDIO_VISIBLE_SELECTED,
                    'values' => array(
                        array('key' => BX_DOL_STUDIO_VISIBLE_ALL, 'value' => _t('_adm_nav_txt_items_visible_for_all')),
                        array('key' => BX_DOL_STUDIO_VISIBLE_SELECTED, 'value' => _t('_adm_nav_txt_items_visible_for_selected')),
                    ),
                    'required' => '0',
                    'attrs' => array(
                        'onchange' => $this->getJsObject() . '.onChangeVisibleFor(this)'
                    ),
                    'db' => array (
                        'pass' => 'Xss',
                    )
                ),
                'visible_for_levels' => array(
                    'type' => 'checkbox_set',
                    'name' => 'visible_for_levels',
                    'caption' => _t('_adm_nav_txt_items_visible_for_levels'),
                    'info' => _t('_adm_nav_dsc_items_visible_for_levels'),
                    'value' => '',
                    'values' => array(),
                    'tr_attrs' => array(
                        'style' => $aItem['visible_for_levels'] == BX_DOL_INT_MAX ? 'display:none' : ''
                    ),
                    'db' => array (
                        'pass' => 'Int',
                    ),
                ),
                'controls' => array(
                    'name' => 'controls',
                    'type' => 'input_set',
                    array(
                        'type' => 'submit',
                        'name' => 'do_submit',
                        'value' => _t('_adm_nav_btn_items_save'),
                    ),
                    array (
                        'type' => 'reset',
                        'name' => 'close',
                        'value' => _t('_adm_nav_btn_items_cancel'),
                        'attrs' => array(
                            'onclick' => "$('.bx-popup-applied:visible').dolPopupHide()",
                            'class' => 'bx-def-margin-sec-left',
                        ),
                    )
                )
            )
        );

        BxDolStudioUtils::getVisibilityValues($aItem['visible_for_levels'], $aForm['inputs']['visible_for_levels']['values'], $aForm['inputs']['visible_for_levels']['value']);

        $oForm = new BxTemplStudioFormView($aForm);
        $oForm->initChecker();

        if($oForm->isSubmittedAndValid()) {
            if($oForm->updateWithVisibility($aItem['id']) !== false)
                $aRes = array('grid' => $this->getCode(false), 'blink' => $aItem['id']);
            else
                $aRes = array('msg' => _t('_adm_nav_err_items_show_to'));

            echoJson($aRes);
        }
        else {
            $sContent = BxTemplStudioFunctions::getInstance()->popupBox('adm-nav-item-hide-from-popup', _t('_adm_nav_txt_items_show_to_popup', _t($aItem['title'])), $this->_oTemplate->parseHtmlByName('nav_add_item.html', array(
                'form_id' => $aForm['form_attrs']['id'],
                'form' => $oForm->getCode(true),
                'object' => $this->_sObject,
                'action' => $sAction
            )));

            echoJson(array('popup' => $sContent));
        }
    }

    public function performActionDeleteIcon()
    {
        $sAction = 'delete_icon';

        $aIds = bx_get('ids');
        if(empty($aIds[0])) {
            echoJson(array());
            exit;
        }

        $iId = (int)$aIds[0];

        $aItem = array();
        $iItem = $this->oDb->getItems(array('type' => 'by_id', 'value' => $iId), $aItem);
        if($iItem != 1 || empty($aItem)){
            echoJson(array());
            exit;
        }

        if(is_numeric($aItem['icon']) && (int)$aItem['icon'] != 0)
            if(!BxDolStorage::getObjectInstance(BX_DOL_STORAGE_OBJ_IMAGES)->deleteFile((int)$aItem['icon'], 0)) {
                echoJson(array());
                exit;
            }

        if($this->oDb->updateItem($aItem['id'], array('icon' => '')) !== false)
            echoJson(array('grid' => $this->getCode(false), 'blink' => $iId, 'preview' => $this->_getIconPreview($aItem['id']), 'eval' => $this->getJsObject() . ".onDeleteIcon(oData)"));
    }

    public function getJsObject()
    {
        return 'oBxDolStudioNavigationItems';
    }

    public function getSetsSelector($sModule = '')
    {
        $oForm = new BxTemplStudioFormView(array());

        $aInputSets = array(
            'type' => 'select',
            'name' => 'set',
            'attrs' => array(
                'id' => 'bx-grid-set-' . $this->_sObject,
                'onChange' => 'javascript:' . $this->getJsObject() . '.onChangeSet()'
            ),
            'value' => $this->sSet,
            'values' => array()
        );

        $aSets = array();
        if(!empty($sModule))
            $this->oDb->getSets(array('type' => 'by_module', 'value' => $sModule), $aSets, false);
        else
            $aInputSets['attrs']['disabled'] = 'disabled';

        if(!empty($aSets)) {
            $aCounter = array();
            $this->oDb->getItems(array('type' => 'counter_by_sets'), $aCounter, false);
            foreach($aSets as $aSet)
                $aInputSets['values'][$aSet['name']] = _t($aSet['title']) . " (" . (isset($aCounter[$aSet['name']]) ? $aCounter[$aSet['name']] : "0") . ")";

            asort($aInputSets['values']);
        }
        $aInputSets['values'] = array_merge(array('' => _t('_adm_nav_txt_select_set')), $aInputSets['values']);

        return $oForm->genRow($aInputSets);
    }

    public function getCode($isDisplayHeader = true)
    {
        return $this->_oTemplate->parseHtmlByName('nav_items.html', array(
            'content' => parent::getCode($isDisplayHeader),
            'js_object' => $this->getJsObject(),
            'page_url' => $this->sUrlPage,
            'grid_object' => $this->_sObject,
            'params_divider' => $this->sParamsDivider
        ));
    }

    protected function _addJsCss()
    {
        parent::_addJsCss();
        $this->_oTemplate->addJs(array('jquery.form.min.js', 'navigation_items.js', 'navigation_import.js'));

        $oForm = new BxTemplStudioFormView(array());
        $oForm->addCssJs();
    }

    protected function _getCellSwitcher ($mixedValue, $sKey, $aField, $aRow)
    {
        if(!$this->_isEditable($aRow))
            return parent::_getCellDefault('', $sKey, $aField, $aRow);

        return parent::_getCellSwitcher($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getCellIcon ($mixedValue, $sKey, $aField, $aRow)
    {
        $mixedValue = $this->_oTemplate->getIcon($mixedValue, array('class' => 'bx-nav-item-icon bx-def-border'));
        return parent::_getCellDefault($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getCellTitleSystem ($mixedValue, $sKey, $aField, $aRow)
    {
        if(empty($mixedValue))
            $mixedValue = _t($aRow['title']);

        return parent::_getCellDefault($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getCellLink ($mixedValue, $sKey, $aField, $aRow)
    {
        $aSubitems = array();
        $this->oDb->getItems(array('type' => 'by_parent_id', 'value' => $aRow['id']), $aSubitems, false);

        if(!empty($aSubitems) && is_array($aSubitems)) {
            $sPrefix = _t('_adm_nav_txt_items_gl_link_subitems');

            $aField['chars_limit'] -= strlen($sPrefix);

            $aTitles = array();
            foreach($aSubitems as $aSubitem)
                $aTitles[] = _t(!empty($aSubitem['title_system']) ? $aSubitem['title_system'] : $aSubitem['title']);

            $aValue = $this->_limitMaxLength(implode(', ', $aTitles), $sKey, $aField, $aRow, $this->_isDisplayPopupOnTextOverflow, false);

            $mixedValue = $sPrefix . ' ' . $aValue[0] . (isset($aValue[1]) ? $aValue[1] : '');
        }
        else if($aRow['submenu_object'] != "") {
            $aMenu = array();
            $this->oDb->getMenus(array('type' => 'by_object', 'value' => $aRow['submenu_object']), $aMenu, false);

            $sPrefix = _t('_adm_nav_txt_items_gl_link_menu');
            if(!empty($aMenu) && is_array($aMenu)) {
                $aField['chars_limit'] -= strlen($sPrefix);

                $aValue = $this->_limitMaxLength(_t($aMenu['title']), $sKey, $aField, $aRow, $this->_isDisplayPopupOnTextOverflow, false);

                $sLink = sprintf($this->sUrlViewItems, $aMenu['module'], $aMenu['set_name']);
                $mixedValue = $sPrefix . ' ' . $this->_oTemplate->parseLink($sLink, $aValue[0], array(
                    'title' => _t('_adm_nav_txt_manage_items') 
                )) . (isset($aValue[1]) ? $aValue[1] : '');
            }
            else 
                $mixedValue = $sPrefix . ' ' . _t('_undefined');
        } 
        else if($aRow['submenu_object'] == "" && $aRow['onclick'] != "")
            $mixedValue = $this->_limitMaxLength(_t('_adm_nav_txt_items_gl_link_custom'), $sKey, $aField, $aRow, $this->_isDisplayPopupOnTextOverflow);
        else
            $mixedValue = BxDolPermalinks::getInstance()->permalink($mixedValue);

        return parent::_getCellDefault($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getCellModule($mixedValue, $sKey, $aField, $aRow)
    {
        $mixedValue = $this->_limitMaxLength($this->getModuleTitle($aRow['module']), $sKey, $aField, $aRow, $this->_isDisplayPopupOnTextOverflow);
        return parent::_getCellDefault($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getCellVisibleForLevels ($mixedValue, $sKey, $aField, $aRow)
    {
        if(!$this->_isEditable($aRow))
            return parent::_getCellDefault('', $sKey, $aField, $aRow);

        $mixedValue = $this->_oTemplate->parseLink('javascript:void(0)', BxDolStudioUtils::getVisibilityTitle($aRow['visible_for_levels']), array(
            'title' => _t('_adm_nav_txt_manage_visibility'),
        	'bx_grid_action_single' => 'show_to',
        	'bx_grid_action_data' => $aRow['id']
        ));

        return parent::_getCellDefault ($mixedValue, $sKey, $aField, $aRow);
    }

    protected function _getActionImport ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        if($this->sSet == '')
            $isDisabled = true;

        return  parent::_getActionDefault($sType, $sKey, $a, false, $isDisabled, $aRow);
    }

    protected function _getActionAdd ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        if($this->sSet == '')
            $isDisabled = true;

        return  parent::_getActionDefault($sType, $sKey, $a, false, $isDisabled, $aRow);
    }

	protected function _getActionEdit ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        if($sType == 'single' && !$this->_isEditable($aRow))
            return '';

        return  parent::_getActionDefault($sType, $sKey, $a, false, $isDisabled, $aRow);
    }

    protected function _getActionDelete ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        if ($sType == 'single'  && !$this->_isDeletable($aRow))
            return '';

        return  parent::_getActionDefault($sType, $sKey, $a, false, $isDisabled, $aRow);
    }

    protected function _getActionShowTo ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        return '';
    }

    protected function _getActionDeleteIcon ($sType, $sKey, $a, $isSmall = false, $isDisabled = false, $aRow = array())
    {
        return '';
    }

    protected function _getActionsDisabledBehavior($aRow)
    {
        return false;
    }

    protected function _getFilterControls ()
    {
        parent::_getFilterControls();

        $sContent = $this->getModulesSelectOne('getItems') . $this->getSetsSelector($this->sModule);

        $oForm = new BxTemplStudioFormView(array());

        $aInputSearch = array(
            'type' => 'text',
            'name' => 'keyword',
            'attrs' => array(
                'id' => 'bx-grid-search-' . $this->_sObject,
            ),
            'tr_attrs' => array(
                'style' => 'display:none;'
            )
        );
        $sContent .= $oForm->genRow($aInputSearch);

        return $sContent;
    }

    protected function _getFormObject($sAction, $aItem = array())
    {
        bx_import('BxTemplStudioFormView');

        $aForm = array(
            'form_attrs' => array(
                'id' => 'adm-nav-item-',
                'action' => BX_DOL_URL_ROOT . 'grid.php?o=' . $this->_sObject . '&a=' . $sAction . '&set=' . $this->sSet,
                'method' => BX_DOL_STUDIO_METHOD_DEFAULT,
                'enctype' => 'multipart/form-data',
            ),
            'params' => array (
                'db' => array(
                    'table' => 'sys_menu_items',
                    'key' => 'id',
                    'uri' => '',
                    'uri_title' => '',
                    'submit_name' => 'do_submit'
                ),
            ),
            'inputs' => array (
                'id' => array(
                    'type' => 'hidden',
                    'name' => 'id',
                    'value' => isset($aItem['id']) ? (int)$aItem['id'] : 0,
                    'db' => array (
                        'pass' => 'Int',
                    ),
                ),
                'set_name' => array(
                    'type' => 'hidden',
                    'name' => 'set_name',
                    'value' => $this->sSet,
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                ),
                'title_system' => array(
                    'type' => 'text_translatable',
                    'name' => 'title_system',
                    'caption' => _t('_adm_nav_txt_items_title_system'),
                    'info' => _t('_adm_nav_dsc_items_title_system'),
                    'value' => isset($aItem['title_system']) ? $aItem['title_system'] : '_adm_nav_txt_item',
                    'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                ),
                'title' => array(
                    'type' => 'text_translatable',
                    'name' => 'title',
                    'caption' => _t('_adm_nav_txt_items_title'),
                    'info' => _t('_adm_nav_dsc_items_title'),
                    'value' => isset($aItem['title']) ? $aItem['title'] : '_adm_nav_txt_item',
                    'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                ),
                'parent_id' => array(
                    'type' => 'select',
                    'name' => 'parent_id',
                    'caption' => _t('_adm_nav_txt_items_parent_id'),
                    'info' => _t('_adm_nav_dsc_items_parent_id'),
                    'value' => isset($aItem['parent_id']) ? $aItem['parent_id'] : '',
                    'values' => array(
                        0 => _t('_adm_nav_txt_items_parent_id_empty')
                    ),
                    'required' => '0',
                    'attrs' => array(),
                    'db' => array (
                        'pass' => 'Int',
                    ),
                ),
                'submenu_object' => array(
                    'type' => 'select',
                    'name' => 'submenu_object',
                    'caption' => _t('_adm_nav_txt_items_submenu'),
                    'info' => _t('_adm_nav_dsc_items_submenu'),
                    'value' => isset($aItem['submenu_object']) ? $aItem['submenu_object'] : '',
                    'values' => array(),
                    'required' => '0',
                    'attrs' => array(
                        'onChange' => 'javascript:' . $this->getJsObject() . '.onChangeSubmenu(this)'
                    ),
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                ),
                'submenu_popup' => array(
                    'type' => 'switcher',
                    'name' => 'submenu_popup',
                    'caption' => _t('_adm_nav_txt_items_submenu_popup'),
                    'info' => '',
                    'value' => '1',
                    'checked' => isset($aItem['submenu_popup']) && $aItem['submenu_popup'] == '1',
                    'attrs' => array(
                        'onChange' => 'javascript:' . $this->getJsObject() . '.onChangeSubmenu(this)'
                    ),
                    'db' => array (
                        'pass' => 'Int',
                    )
                ),
                'link' => array(
                    'type' => 'text',
                    'name' => 'link',
                    'caption' => _t('_adm_nav_txt_items_link'),
                    'info' => _t('_adm_nav_dsc_items_link'),
                    'value' => isset($aItem['link']) ? $aItem['link'] : '',
                    'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                    'checker' => array (
                        'func' => '',
                        'params' => array(),
                        'error' => _t('_adm_nav_err_items_link'),
                    ),
                ),
                'target' => array(
                    'type' => 'select',
                    'name' => 'target',
                    'caption' => _t('_adm_nav_txt_items_target'),
                    'info' => _t('_adm_nav_dsc_items_target'),
                    'value' => isset($aItem['target']) ? $aItem['target'] : '_self',
                    'values' => array(
                        array('key' => '', 'value' => _t('_adm_nav_txt_items_target_self')),
                        array('key' => '_blank', 'value' => _t('_adm_nav_txt_items_target_blank'))
                    ),
                    'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    )
                ),
                'onclick' => array(
                    'type' => 'text',
                    'name' => 'onclick',
                	'caption' => _t('_adm_nav_txt_items_onclick'),
                	'info' => _t('_adm_nav_dsc_items_onclick'),
                    'value' => isset($aItem['onclick']) ? $aItem['onclick'] : '',
                	'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                    'checker' => array (
                        'func' => '',
                        'params' => array(),
                        'error' => _t('_adm_nav_err_items_onclick'),
                    ),
                ),
                'hidden_on' => array(
                    'type' => 'select_multiple',
                    'name' => 'hidden_on',
                    'caption' => _t('_adm_nav_txt_block_hidden_on'),
                    'info' => '',
                    'value' => isset($aItem['hidden_on']) ? (int)$aItem['hidden_on'] : '',
                    'values' => array(
                        BX_DB_HIDDEN_PHONE => _t('_adm_nav_txt_block_hidden_on_phone'),
                        BX_DB_HIDDEN_TABLET => _t('_adm_nav_txt_block_hidden_on_tablet'),
                        BX_DB_HIDDEN_DESKTOP => _t('_adm_nav_txt_block_hidden_on_desktop'),
                        BX_DB_HIDDEN_MOBILE => _t('_adm_nav_txt_block_hidden_on_mobile')
                    ),
                    'db' => array (
                        'pass' => 'Set',
                    )
                ),
                'icon' => array(
                    'type' => 'text',
                    'name' => 'icon',
                    'caption' => _t('_adm_nav_txt_items_icon'),
                    'info' => _t('_adm_nav_dsc_items_icon'),
                    'value' => '',
                    'required' => '0',
                    'db' => array (
                        'pass' => 'Xss',
                    ),
                    'checker' => array (
                        'func' => '',
                        'params' => array(),
                        'error' => _t('_adm_nav_err_items_icon'),
                    ),
                ),
                'icon_image' => array(
                    'type' => 'file',
                    'name' => 'icon_image',
                    'caption' => _t('_adm_nav_txt_items_icon_image'),
                    'info' => _t('_adm_nav_dsc_items_icon_image'),
                    'value' => '',
                    'checker' => array (
                        'func' => '',
                        'params' => '',
                        'error' => _t('_adm_nav_err_items_icon_image'),
                    ),
                ),
                'icon_preview' => array(
                    'type' => 'custom',
                    'name' => 'icon_preview',
                    'caption' => _t('_adm_nav_txt_items_icon_image_old'),
                    'content' => ''
                ),
                'controls' => array(
                    'name' => 'controls',
                    'type' => 'input_set',
                    array(
                        'type' => 'submit',
                        'name' => 'do_submit',
                        'value' => _t('_adm_nav_btn_items_add'),
                    ),
                    array (
                        'type' => 'reset',
                        'name' => 'close',
                        'value' => _t('_adm_nav_btn_items_cancel'),
                        'attrs' => array(
                            'onclick' => "$('.bx-popup-applied:visible').dolPopupHide()",
                            'class' => 'bx-def-margin-sec-left',
                        ),
                    )
                )
            )
        );
        
        $aItems = array();
        $this->oDb->getItems(array('type' => 'by_set_name', 'value' => $this->sSet), $aItems, false);
        foreach($aItems as $aItem)
            $aForm['inputs']['parent_id']['values'][$aItem['id']] = _t(!empty($aItem['title_system']) ? $aItem['title_system'] : $aItem['title']);

        $aMenus = array();
        $this->oDb->getMenus(array('type' => 'all'), $aMenus, false);
        foreach($aMenus as $aMenu)
            $aForm['inputs']['submenu_object']['values'][$aMenu['object']] = _t($aMenu['title']);

        asort($aForm['inputs']['submenu_object']['values']);
        $aForm['inputs']['submenu_object']['values'] = array_merge(array('' => _t('_adm_nav_txt_items_submenu_empty')), $aForm['inputs']['submenu_object']['values']);

        switch($sAction) {
            case 'add':
                unset($aForm['inputs']['id']);
                unset($aForm['inputs']['icon_preview']);

                $aForm['form_attrs']['id'] .= 'create';
                break;

            case 'edit':
                unset($aForm['inputs']['set_name']);

                $aForm['form_attrs']['id'] .= 'edit';
                $aForm['inputs']['icon_image']['caption'] = _t('_adm_nav_txt_items_icon_image_new');
                $aForm['inputs']['controls'][0]['value'] = _t('_adm_nav_btn_items_save');

                $bSubmenu = !empty($aItem['submenu_object']);
                if($bSubmenu !== true)
                	$aForm['inputs']['submenu_popup']['tr_attrs']['style'] = 'display:none;';

                if(!$bSubmenu && ($aItem['onclick'] != "" || !in_array($aItem['target'], array('', '_blank')))) {
                    $aForm['inputs']['submenu_object']['tr_attrs']['style'] = 'display:none;';
                    $aForm['inputs']['link']['tr_attrs']['style'] = 'display:none;';
                    $aForm['inputs']['target']['tr_attrs']['style'] = 'display:none;';
                }

                $sIconImage = $sIconFont = "";
                if(!empty($aItem['icon'])) {
                    if(is_numeric($aItem['icon']) && (int)$aItem['icon'] != 0) {
                        $oStorage = BxDolStorage::getObjectInstance(BX_DOL_STORAGE_OBJ_IMAGES);

                        $sIconImage = $oStorage->getFileUrlById((int)$aItem['icon']);
                    }
                    else {
                        $sIconFont = $aItem['icon'];
                        $aForm['inputs']['icon']['value'] = $sIconFont;
                    }
                }

                $aForm['inputs']['icon_preview']['content'] = $this->_getIconPreview($aItem['id'], $sIconImage, $sIconFont);
                break;
        }

        return  new BxTemplStudioFormView($aForm);
    }

    protected function _getIconPreview($iId, $sIconImage = '', $sIconFont = '')
    {
        $bIconImage = !empty($sIconImage);
        $bIconFont = !empty($sIconFont);

        return $this->_oTemplate->parseHtmlByName('nav_item_icon_preview.html', array(
            'id' => $iId,
            'bx_if:show_icon_empty' => array(
                'condition' => !$bIconImage && !$bIconFont,
                'content' => array()
            ),
            'bx_if:show_icon_image' => array(
                'condition' => $bIconImage,
                'content' => array(
                    'js_object' => $this->getJsObject(),
                    'url' => $sIconImage,
                    'id' => $iId
                )
            ),
            'bx_if:show_icon_font' => array(
                'condition' => $bIconFont,
                'content' => array(
                    'icon' => $sIconFont
                )
            )
        ));
    }

    protected function _isEditable(&$aRow)
    {
    	return (int)$aRow['editable'] != 0;
    }

	protected function _isDeletable(&$aRow)
    {
    	return $aRow['module'] != BX_DOL_STUDIO_MODULE_SYSTEM;
    }
}

/** @} */
