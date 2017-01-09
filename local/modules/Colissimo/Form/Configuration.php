<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Colissimo\Form;

use Colissimo\Colissimo;
use Colissimo\Model\Config\ColissimoConfigValue;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Configuration
 * @package Colissimo\Form
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class Configuration extends BaseForm
{
    /** @var Translator */
    protected $translator = null;

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "enabled",
                "checkbox",
                array(
                    "label" => "Enabled",
                    "label_attr" => [
                        "for" => "enabled",
                        "help" => $this->trans('Check if you want to activate Colissimo')
                    ],
                    "required" => false,
                    "constraints" => array(
                    ),
                    "value" => Colissimo::getConfigValue(ColissimoConfigValue::ENABLED, 1),
                )
            )
            ->add(
                'export_type',
                'choice',
                [
                    'choices' => [
                        ColissimoConfigValue::EXPORT_COLISHIP => $this->trans('Coliship'),
                        ColissimoConfigValue::EXPORT_EXPEDITOR => $this->trans('Expeditor'),
                    ],
                    'constraints' => [
                        new Callback(
                            array("methods" => array(array($this, "verifyValue")))
                        )
                    ],
                    'label' => $this->trans('Export type'),
                    'label_attr' => [
                        'for' => 'export_type'
                    ],
                    'data' => Colissimo::getConfigValue(
                        ColissimoConfigValue::EXPORT_TYPE,
                        ColissimoConfigValue::EXPORT_COLISHIP
                    )
                ]
            )
            ->add(
                'account_number',
                'text',
                [
                    'label' => $this->trans('Billing account number'),
                    'label_attr' => [
                        'for' => 'account_number'
                    ],
                    'required' => false,
                    'data' => Colissimo::getConfigValue(ColissimoConfigValue::ACCOUNT_NUMBER)
                ]
            )
            ->add(
                'sender_code',
                'text',
                [
                    'label' => $this->trans('Sender code'),
                    'label_attr' => [
                        'for' => 'sender_code',
                        'help' => $this->trans('Sender address code in Coliship address book')
                    ],
                    'required' => false,
                    'data' => Colissimo::getConfigValue(ColissimoConfigValue::SENDER_CODE)
                ]
            )
            ->add(
                'default_product',
                'choice',
                [
                    'label' => $this->trans('Default product'),
                    'choices' => ColissimoConfigValue::getProducts(),
                    'label_attr' => [
                        'for' => 'sender_code',
                        'help' => $this->trans('The default Colissimo product to use')
                    ],
                    'required' => false,
                    'data' => Colissimo::getConfigValue(ColissimoConfigValue::DEFAULT_PRODUCT)
                ]
            )
        ;
    }

    public function verifyValue($value, ExecutionContextInterface $context)
    {
        if (ColissimoConfigValue::EXPORT_COLISHIP == $value) {
            $data = $context->getRoot()->getData();

            $senderCode = $data["sender_code"];
            $accountNumber = $data["account_number"];

            if (empty($senderCode) || empty($accountNumber)) {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        'For Coliship export, you should provide an account number and a sender code',
                        [],
                        Colissimo::DOMAIN_NAME
                    )
                );
            }


        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return "colissimo_enable";
    }

    protected function trans($id, $parameters = [], $locale = null)
    {
        if (null === $this->translator) {
            $this->translator = Translator::getInstance();
        }
        return $this->translator->trans($id, $parameters, Colissimo::DOMAIN_NAME, $locale);
    }
}
