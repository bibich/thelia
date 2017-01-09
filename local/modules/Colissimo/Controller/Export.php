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

namespace Colissimo\Controller;

use Colissimo\Colissimo;
use Colissimo\Form\Export as FormExport;
use Colissimo\Model\ColissimoQuery;
use Colissimo\Model\Config\ColissimoConfigValue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatusQuery;

/**
 * Class Export
 * @package Colissimo\Controller
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Export extends BaseAdminController
{
    const DEFAULT_PHONE = "0100000000";
    const DEFAULT_CELLPHONE = "0600000000";

    const PRODUCT_CODE = '';

    public function exportAction()
    {
        $response = $this->checkAuth(array(AdminResources::MODULE), array('Colissimo'), AccessManager::UPDATE);
        if (null !== $response) {
            return $response;
        }

        $form = new FormExport($this->getRequest());

        try {
            $exportType = Colissimo::getConfigValue(
                ColissimoConfigValue::EXPORT_TYPE,
                ColissimoConfigValue::EXPORT_COLISHIP
            );
            if (ColissimoConfigValue::EXPORT_COLISHIP) {
                $accountNumber = Colissimo::getConfigValue(ColissimoConfigValue::ACCOUNT_NUMBER);
                $senderCode = Colissimo::getConfigValue(ColissimoConfigValue::SENDER_CODE);
                if (null === $accountNumber || null === $senderCode) {
                    throw new \Exception($this->getTranslator()->trans(
                        'You must fill in your account number and sender code before'
                    ));
                }
            }

            $exportForm = $this->validateForm($form);

            // Get new status
            $status_id = $exportForm->get('status_id')->getData();
            $status = OrderStatusQuery::create()
                ->filterByCode($status_id)
                ->findOne();

            // Get Colissimo orders
            $orders = ColissimoQuery::getOrders()->find();

            $storeName = ConfigQuery::getStoreName();
            $lines = [];
            
            /** @var $order \Thelia\Model\Order */
            foreach ($orders as $order) {

                $value = $exportForm->get('order_' . $order->getId())->getData();

                if ($value) {

                    // Get order information
                    $customer = $order->getCustomer();
                    $locale = $order->getLang()->getLocale();
                    $address = $order->getOrderAddressRelatedByDeliveryOrderAddressId();
                    $country = CountryQuery::create()->findPk($address->getCountryId());
                    $country->setLocale($locale);
                    $customerTitle = CustomerTitleQuery::create()->findPk($address->getCustomerTitleId());
                    $customerTitle->setLocale($locale);
                    $productCode = $exportForm->get('order_product_code_' . $order->getId())->getData();
                    $weight = $exportForm->get('order_weight_' . $order->getId())->getData();

                    if ($weight == 0) {
                        /** @var \Thelia\Model\OrderProduct $product */
                        foreach ($order->getOrderProducts() as $product) {
                            $weight += (double)$product->getWeight();
                        }
                    }

                    /**
                     * Get user's phone & cellphone
                     * First get invoice address phone,
                     * If empty, try to get default address' phone.
                     * If still empty, set default value
                     */
                    $phone = $address->getPhone();
                    if (empty($phone)) {
                        $phone = $customer->getDefaultAddress()->getPhone();
                        if (empty($phone)) {
                            $phone = self::DEFAULT_PHONE;
                        }
                    }

                    // Cellphone
                    $cellphone = $customer->getDefaultAddress()->getCellphone();
                    if (empty($cellphone)) {
                        $cellphone = $customer->getDefaultAddress()->getCellphone();
                        if (empty($cellphone)) {
                            $cellphone = self::DEFAULT_CELLPHONE;
                        }
                    }

                    if ($exportType == ColissimoConfigValue::EXPORT_EXPEDITOR) {
                        $columns = $this->exportOrderForExpeditor(
                            $order,
                            $address,
                            $country,
                            $customer,
                            $phone,
                            $cellphone,
                            $productCode,
                            $weight,
                            $storeName
                        );
                    } else {
                        $columns = $this->exportOrderForColiship(
                            $order,
                            $address,
                            $country,
                            $customer,
                            $accountNumber,
                            $senderCode,
                            $storeName,
                            $phone,
                            $cellphone,
                            $productCode,
                            $weight
                        );
                    }

                    $lines[] = $this->encodeLine($columns);

                    if ($status) {
                        $event = new OrderEvent($order);
                        $event->setStatus($status->getId());
                        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
                    }
                }
            }

            return Response::create(
                utf8_decode(implode($lines, "\r\n")),
                200,
                array(
                    "Content-Encoding" => "ISO-8889-1",
                    "Content-Type" => "application/csv-tab-delimited-table",
                    "Content-disposition" => "filename=export.csv"
                )
            );

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("colissimo export", [], Colissimo::DOMAIN_NAME),
                $e->getMessage(),
                $form,
                $e
            );

            return $this->render(
                "module-configure",
                array(
                    "module_code" => "Colissimo",
                    'products' => ColissimoConfigValue::getProducts(),
                    'default_product' => Colissimo::getConfigValue(ColissimoConfigValue::DEFAULT_PRODUCT),
                )
            );
        }
    }

    /**
     * generate line columns for coliship export
     * @rturn array
     */
    protected function exportOrderForColiship(
        Order $order,
        OrderAddress $address,
        Country $country,
        Customer $customer,
        $accountNumber,
        $senderCode,
        $storeName,
        $phone,
        $cellphone,
        $productCode,
        $weight
    ) {
        $cols = array_fill(0, 114, '');

        $cols[0] = 'CLS';
        $cols[1] = $accountNumber;
        $cols[2] = $order->getRef();
        $cols[3] = $productCode;
        $cols[5] = $storeName;
        $cols[6] = $weight;
        $cols[14] = $senderCode;

        $cols[37] = $address->getLastname();
        $cols[38] = $address->getFirstname();
        $cols[39] = $address->getAddress1();
        $cols[40] = $address->getAddress2();
        $cols[41] = $address->getAddress3();
        $cols[43] = $country->getIsoalpha2();
        $cols[44] = $address->getCity();
        $cols[45] = $address->getZipcode();

        $cols[46] = $phone;
        $cols[47] = $cellphone;

        $cols[52] = $customer->getEmail();

        return $cols;
    }

    /**
     * generate columns for an expeditor inet export
     *
     * @return array
     */
    protected function exportOrderForExpeditor(
        Order $order,
        OrderAddress $address,
        Country $country,
        Customer $customer,
        $phone,
        $cellphone,
        $productCode,
        $weight,
        $store_name
    ) {
        $columns = [
            $order->getRef(),
            $address->getLastname(),
            $address->getFirstname(),
            $address->getAddress1(),
            $address->getAddress2(),
            $address->getAddress3(),
            $address->getZipcode(),
            $address->getCity(),
            $country->getIsoalpha2(),
            $phone,
            $cellphone,
            $weight,
            $customer->getEmail(),
            '',
            $store_name,
            $productCode
        ];

        return $columns;
    }

    protected function encodeLine($cols)
    {
        for ($i = 0; $i < count($cols); $i++) {
            if ($cols[$i] === null) {
                $cols[$i] = '';
            }
            $cols[$i] = '"' . str_replace('"', '""', $cols[$i]) . '"';
        }

        return implode(';', $cols);
    }
}
