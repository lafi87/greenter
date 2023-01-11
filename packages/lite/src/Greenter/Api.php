<?php

declare(strict_types=1);

namespace Greenter;

use Greenter\Api\ApiFactory;
use Greenter\Api\GreSender;
use Greenter\Api\InMemoryStore;
use Greenter\Factory\XmlBuilderResolver;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BaseResult;
use Greenter\Model\Response\StatusResult;
use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\ApiException;
use Greenter\Sunat\GRE\Configuration;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use GuzzleHttp\Client;

class Api
{
    private ?ApiFactory $factory = null;
    private ?SignedXml $signer = null;

    private array $credentials = [];
    private array $endpoints = [
        'api' => 'https://api-seguridad.sunat.gob.pe/v1',
        'cpe' => 'https://api.sunat.gob.pe/v1',
    ];

    /**
     * Twig Render Options.
     */
    private array $options = ['autoescape' => false];

    /**
     * @param ApiFactory|null $factory
     * @param SignedXml|null $signer
     */
    public function __construct(?ApiFactory $factory, ?SignedXml $signer)
    {
        $this->factory = $factory ?? $this->createApiFactory();
        $this->signer = $signer ?? new SignedXml();
    }

    /**
     * Set Xml Builder Options.
     *
     * @param array $options
     *
     * @return Api
     */
    public function setBuilderOptions(array $options): Api
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * @param array $endpoints
     *
     * @return Api
     */
    public function setEndpoint(array $endpoints): Api
    {
        $this->endpoints = $endpoints;

        return $this;
    }

    /**
     * @param string $client_id
     * @param string $secret
     *
     * @return Api
     */
    public function setApiCredentials(string $client_id, string $secret): Api
    {
        $this->credentials['client_id'] = $client_id;
        $this->credentials['client_secret'] = $secret;

        return $this;
    }

    /**
     * Set Clave SOL de usuario secundario.
     *
     * @param string $ruc
     * @param string $user
     * @param string $password
     *
     * @return Api
     */
    public function setClaveSOL(string $ruc, string $user, string $password): Api
    {
        $this->credentials['username'] = $ruc.$user;
        $this->credentials['password'] = $password;

        return $this;
    }

    /**
     * @param string $certificate
     *
     * @return Api
     */
    public function setCertificate(string $certificate): Api
    {
        $this->signer->setCertificate($certificate);

        return $this;
    }

    /**
     * Envia comprobante.
     *
     * @param DocumentInterface $document
     *
     * @return BaseResult|null
     * @throws ApiException
     */
    public function send(DocumentInterface $document): ?BaseResult
    {
        $buildResolver = new XmlBuilderResolver($this->options);
        $builder = $buildResolver->find(get_class($document));
        $sender = $this->createSender();

        $xml = $builder->build($document);
        $xmlSigned = $this->signer->signXml($xml);
        return $sender->send($document->getName(), $xmlSigned);
    }

    /**
     * Consultar el estado del envio.
     *
     * @param string|null $ticket
     * @return StatusResult
     * @throws ApiException
     */
    public function getStatus(?string $ticket): StatusResult {
        $sender = $this->createSender();

        return $sender->status($ticket);
    }

    /**
     * @throws ApiException
     */
    private function createSender(): GreSender {
        $api = $this->factory->create(
            $this->credentials['client_id'],
            $this->credentials['client_secret'],
            $this->credentials['username'],
            $this->credentials['password']
        );

        return new GreSender($api);
    }

    private function createApiFactory(): ApiFactory {
        $client = new Client();
        $config = Configuration::getDefaultConfiguration();

        return new ApiFactory(
            new AuthApi($client, $config->setHost($this->endpoints['api'])),
            $client,
            new InMemoryStore(),
            $this->endpoints['cpe'],
        );
    }
}
