<?php
namespace Collecting\Form\Element;

use Collecting\Validator\Recaptcha as RecaptchaValidator;
use Zend\Form\Element;
use Zend\Http\Client;
use Zend\InputFilter\InputProviderInterface;
use Zend\Validator\ValidatorInterface;

class Recaptcha extends Element implements InputProviderInterface
{
    protected $attributes = [
        'type' => 'recaptcha',
        'name' => 'g-recaptcha-response',
        'class' => 'g-recaptcha',
    ];

    protected $siteKey;

    protected $secretKey;

    protected $remoteIp;

    protected $client;

    public function setOptions($options)
    {
        parent::setOptions($options);

        if (isset($this->options['site_key'])) {
            $this->setSiteKey($this->options['site_key']);
        }
        if (isset($this->options['secret_key'])) {
            $this->setSecretKey($this->options['secret_key']);
        }
        if (isset($this->options['remote_ip'])) {
            $this->setRemoteIp($this->options['remote_ip']);
        }

        return $this;
    }

    public function setSiteKey($siteKey)
    {
        $this->siteKey = $siteKey;
        $this->setAttribute('data-sitekey', $siteKey);
        return $this;
    }

    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
        return $this;
    }

    public function setRemoteIp($remoteIp)
    {
        $this->remoteIp = $remoteIp;
        return $this;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

    public function getInputSpecification()
    {
        return [
            'name' => 'g-recaptcha-response',
            'required' => true,
            'validators' => [
                [
                    'name' => 'NotEmpty',
                    'options' => [
                        'messages' => [
                            'isEmpty' => 'You must verify that you are human by completing the CAPTCHA.', // @translate
                        ],
                    ],
                ],
                [
                    'name' => 'Callback',
                    'options' => [
                        'callback' => [$this, 'isValid'],
                        'messages' => [
                            'callbackValue' => 'Could not verify that you are a human.', // @translate
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Validate the reCAPTCHA.
     *
     * @param string $value
     * @return bool
     */
    public function isValid($value)
    {
        $response = $this->client
            ->setUri('https://www.google.com/recaptcha/api/siteverify')
            ->setMethod('POST')
            ->setParameterPost([
                'response' => $value,
                'secret' => $this->secretKey,
                'remoteip' => $this->remoteIp,
            ])->send();
        $apiResponse = json_decode($response->getBody(), true);
        if ($apiResponse['success']) {
            return true;
        }
        return false;
    }
}
