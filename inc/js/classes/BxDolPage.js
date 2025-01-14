/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    UnaCore UNA Core
 * @{
 */
function BxDolPage(oOptions) {
    this._sObjName = oOptions.sObjName == undefined ? 'oBxDolPage' : oOptions.sObjName;
    this._sObject = oOptions.sObject; // page object

    this._sActionsUri = 'page.php';
    this._sActionsUrl = oOptions.sRootUrl + this._sActionsUri; // actions url address
    this._aHtmlIds = oOptions.aHtmlIds;

    this._isStickyColumns = oOptions.isStickyColumns == undefined ? false : oOptions.isStickyColumns;
    this._iLastSc = 0;

    var $this = this;
    $(document).ready(function() {
        $this.init();
    });
}

BxDolPage.prototype.init = function() {
    var $this = this;
    if ($this._isStickyColumns && !$('html').hasClass('bx-media-phone')) {
        $('.bx-layout-col').theiaStickySidebar({
            additionalMarginTop: 30
        });
    }
};

BxDolPage.prototype.showHelp = function(oLink, iBlockId)
{
    var oData = this._getDefaultParams();
    oData['a'] = 'get_help';
    oData['block_id'] = iBlockId;

    $(oLink).dolPopupAjax({
        id: {value:this._aHtmlIds['help_popup'] + iBlockId, force:1}, 
        url: bx_append_url_params(this._sActionsUri, oData),
        closeOnOuterClick: true,
        removeOnClose: false,
    });
};

BxDolPage.prototype._getDefaultParams = function() 
{
    var oDate = new Date();
    return {
        o: this._sObject,
        _t: oDate.getTime()
    };
};

/** @} */
