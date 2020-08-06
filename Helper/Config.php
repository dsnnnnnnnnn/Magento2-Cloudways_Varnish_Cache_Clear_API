<?php

namespace Red\Iguana\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    public const BASE_CONFIG_XML_PREFIX  = 'cloudways_api/settings/%s';

    public const STATUS                  = 'enabled';
    public const CLOUDWAYS_SERVER_NAME   = 'cloudways_server_name';
    public const CLOUDWAYS_API_KEY       = 'cloudways_api_key';
    public const CLOUDWAYS_EMAIL         = 'cloudways_email';

    /**
     * Config constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(\Magento\Framework\App\Helper\Context $context)
    {
        parent::__construct($context);
    }

    /**
     * @param $configField
     * @return mixed
     */
    public function getConfigParam($configField): string
    {
        return $this->scopeConfig->getValue(
            sprintf(self::BASE_CONFIG_XML_PREFIX, $configField)
        );
    }

    public function isEnabled($status = self::STATUS): bool
    {
        return $this->scopeConfig->isSetFlag(
            sprintf(self::BASE_CONFIG_XML_PREFIX, $status)
        );
    }
}