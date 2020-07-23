<?php
declare(strict_types=1);

namespace Red\Iguana\Observer;

/**
 * Class CloudwaysAPIClient
 */
class CloudwaysAPIClient implements \Magento\Framework\Event\ObserverInterface
{
    const API_URL = "https://api.cloudways.com/api/v1";

    private $authKey;
    private $authEmail;
    private $accessToken;

    public function __construct()
    {
        $this->authKey = 'your_key';
        $this->authEmail = 'your_email';
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
            if ($server->label === 'your_server_name' && $server->status === 'running') {
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

    private function getServers(): array
    {
        $response = $this->request('GET', '/server');

        if ($response->status === true) {
            return $response->servers;
        }

        return [];
    }

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

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->purgeCache();
    }
}
