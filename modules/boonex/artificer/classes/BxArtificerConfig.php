<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    Artificer Artificer template
 * @ingroup     UnaModules
 *
 * @{
 */

bx_import('BxBaseModTemplateConfig');

class BxArtificerConfig extends BxBaseModTemplateConfig
{
    function __construct($aModule)
    {
        parent::__construct($aModule);

        $this->_aPrefixes = array(
            'option' => 'bx_artificer_'
        );
    }
}

/** @} */
