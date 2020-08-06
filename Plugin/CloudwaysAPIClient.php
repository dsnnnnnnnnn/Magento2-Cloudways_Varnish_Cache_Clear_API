<?php
declare(strict_types=1);

namespace Red\Iguana\Plugin;

/**
 * Class CloudwaysAPIClient
 */
class CloudwaysAPIClient
{
    const API_URL = "https://api.cloudways.com/api/v1";

    /**
     * @var bool $isEnable
     */
    private $isEnable;

    /**
     * @var mixed|string $serverName
     */
    private $serverName;

    /**
     * @var mixed|string $authKey
     */
    private $authKey;

    /**
     * @var mixed|string $authEmail
     */
    private $authEmail;

    /**
     * @var $accessToken
     */
    private $accessToken;

    /**
     * @var \Red\Iguana\Helper\Config $configHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\PageCache\Model\Config $config
     */
    protected $config;

    /**
     * CloudwaysAPIClient constructor.
     * @param \Magento\PageCache\Model\Config $config
     * @param \Red\Iguana\Helper\Config $configHelper
     */
    public function __construct(
        \Magento\PageCache\Model\Config $config,
        \Red\Iguana\Helper\Config $configHelper
    ) {
        $this->config = $config;
        $this->serverName = $configHelper->getConfigParam(\Red\Iguana\Helper\Config::CLOUDWAYS_SERVER_NAME);
        $this->authKey = $configHelper->getConfigParam(\Red\Iguana\Helper\Config::CLOUDWAYS_API_KEY);
        $this->authEmail = $configHelper->getConfigParam(\Red\Iguana\Helper\Config::CLOUDWAYS_EMAIL);
        $this->isEnable = $configHelper->isEnabled();
        $this->prepareAccessToken();
    }

    private function prepareAccessToken(): void
    {
        $data = [
            'email' => $this->authEmail,
            'api_key' => $this->authKey
        ];
        $response = $this->request('POST', '/oauth/access_token', $data);
        $this->accessToken = $response->access_token;
    }

    public function purgeCache()
    {
        foreach ($this->getServers() as $server) {
            if ($server->label === $this->serverName && $server->status === 'running') {
                $data = [
                    'server_id' => $server->id,
                    'action' => 'purge'
                ];

                $response = $this->request('POST', '/service/varnish', $data);

                if ($response->response->status !== 'Done') {
                    throw new \RuntimeException('Unknown response status from the Cloudways API (may not be a problem)');
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getServers(): array
    {
        $response = $this->request('GET', '/server');

        if ($response->status === true) {
            return $response->servers;
        }

        return [];
    }

    /**
     * @param $method
     * @param $url
     * @param array $post
     * @return mixed
     */
    private function request($method, $url, $post = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        do {
            if ($this->accessToken) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->accessToken]);
            }

            $encoded = '';

            if (count($post)) {
                foreach ($post as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                $encoded = substr($encoded, 0, -1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            $output = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            # ACCESS TOKEN HAS EXPIRED, so regenerate and retry
            if ($httpCode === 401) {
                $this->prepareAccessToken();
            }
        } while ($httpCode === 401);

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                'An error occurred code: ' . $httpCode . ' output: ' . substr($output, 0, 10000)
            );
        }
        curl_close($ch);

        return json_decode($output, false);
    }

    /**
     * @param \Magento\Framework\App\Cache\TypeList $subject
     * @param $typeCode
     */
    public function beforeCleanType(\Magento\Framework\App\Cache\TypeList $subject, $typeCode)
    {
        if ($this->isEnable && $this->config->isEnabled() && $typeCode == 'full_page') {
            $this->purgeCache();
        }
    }
}