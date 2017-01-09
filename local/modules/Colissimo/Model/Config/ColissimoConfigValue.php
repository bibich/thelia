<?php
/*************************************************************************************/
/* This file is part of the Thelia package.                                          */
/*                                                                                   */
/* Copyright (c) OpenStudio                                                          */
/* email : dev@thelia.net                                                            */
/* web : http://www.thelia.net                                                       */
/*                                                                                   */
/* For the full copyright and license information, please view the LICENSE.txt       */
/* file that was distributed with this source code.                                  */
/*************************************************************************************/

namespace Colissimo\Model\Config;

use Colissimo\Colissimo;
use Thelia\Core\Translation\Translator;

/**
 * Class Colissimo
 * @package Colissimo\Model\Config
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class ColissimoConfigValue
{
    const FREE_SHIPPING = 'free_shipping';
    const PRICES = 'prices';
    const ENABLED = 'enabled';
    const EXPORT_TYPE = 'export_type';
    const ACCOUNT_NUMBER = 'account_number';
    const SENDER_CODE = 'sender_code';
    const DEFAULT_PRODUCT = 'default_product';

    const EXPORT_COLISHIP = 'coliship';
    const EXPORT_EXPEDITOR = 'expeditor';

    public static function getProducts()
    {
        $t = Translator::getInstance();

        $products = [
            'DOM' => $t->trans('Colissimo Domicile - sans signature', [], Colissimo::DOMAIN_NAME),
            'DOS' => $t->trans('Colissimo Domicile - avec signature', [], Colissimo::DOMAIN_NAME),
            'COM' => $t->trans('Colissimo Domicile Outre-Mer - sans signature', [], Colissimo::DOMAIN_NAME),
            'CDS' => $t->trans('Colissimo Domicile Outre-Mer - avec signature', [], Colissimo::DOMAIN_NAME),
            'ECO' => $t->trans('Colissimo Eco Outre-Mer', [], Colissimo::DOMAIN_NAME),
            'COLI' => $t->trans('International Colissimo Expert (outside Europe)', [], Colissimo::DOMAIN_NAME),
        ];

        return $products;
    }
}
