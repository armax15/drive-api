<?php


namespace root;


use Google_Client;
use Google_Service_Drive;
use RuntimeException;

class GoogleDriveApi
{
    private ClientSettings $clientSettings;
    private Google_Client $client;
    private Google_Service_Drive $service;

    public function __construct(ClientSettings $settings)
    {
        $this->clientSettings = $settings;
    }

    /**
     * @return Google_Service_Drive
     * @throws \Google\Exception
     */
    public function getGoogleDriveService(): Google_Service_Drive
    {
        if (!isset($this->service)) {
            $this->createClientIfNotExists();
            $this->setupToken();
            $this->service = new Google_Service_Drive($this->client);
        }

        return $this->service;
    }

    /**
     * @throws \Google\Exception
     */
    private function createClientIfNotExists(): void
    {
        if (isset($this->client)) {
            return;
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName($this->clientSettings->getAppName());
        $this->client->setScopes($this->clientSettings->getScopes());
        $this->client->setAuthConfig($this->clientSettings->getAuthData());
        $this->client->setAccessType($this->clientSettings->getAccessType());
        $this->client->setPrompt('select_account consent');
    }

    /**
     * @return bool
     */
    private function setupToken(): bool
    {
        $tokenPath = $this->clientSettings->getTokenPath();
        $isTokenFileExists = file_exists($tokenPath);
        if ($isTokenFileExists) {
            $tokenArray = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($tokenArray);
        }

        if (!$this->client->isAccessTokenExpired()) {

            return true;
        }

        $tokenDir = dirname($tokenPath);
        if (
            !$isTokenFileExists
            && !file_exists($tokenDir)
            && !mkdir($tokenDir, 0600, true)
            && !is_dir($tokenDir)
        ) {
            throw new RuntimeException(sprintf('Directory [%s] was not created', $tokenDir));
        }

        $token = json_encode($this->getTokenArray());

        return file_put_contents($tokenPath, $token) > 0;
    }

    /**
     * @return array
     */
    private function getTokenArray(): array
    {
        if ($this->client->getRefreshToken()) {
            return $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
        }

        $authUrl = $this->client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);

        if (array_key_exists('error', $accessToken)) {
            throw new RuntimeException(implode(', ', $accessToken));
        }

        return $accessToken;
    }
}