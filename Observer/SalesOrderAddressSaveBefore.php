<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2019  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace DPDBenelux\Shipping\Observer;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\OrderRepository;

/**
 * Class SalesOrderAddressSaveBefore
 * @package DPDBenelux\Shipping\Observer
 */
class SalesOrderAddressSaveBefore implements ObserverInterface
{

    /**
     * @var State
     */
    private $state;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * SalesOrderAddressSaveBefore constructor.
     * @param State $state
     * @param ScopeConfigInterface $scopeConfig
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        State $state,
        ScopeConfigInterface $scopeConfig,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->state = $state;
        $this->scopeConfig = $scopeConfig;
        $this->attributeRepository = $attributeRepository;
    }

    public function execute(Observer $observer)
    {
        // Ignore adminhtml
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            return;
        }

        /** @var OrderAddressInterface $shippingAddress */
        $shippingAddress = $observer->getEvent()->getAddress();

        /** @var OrderInterface $order */
        $order = $shippingAddress->getOrder();

        // Ignore all orders that aren't dpd pickup
        if ($order->getShippingMethod() != 'dpdpickup_dpdpickup') {
            return;
        }

        // If the address isn't the shipping address
        if ($shippingAddress->getAddressType() != 'shipping') {
            return;
        }


        $shippingAddress->setFirstname('DPD ParcelShop:');
        $shippingAddress->setLastname($order->getDpdCompany());
        $shippingAddress->setStreet($order->getDpdStreet());
        $shippingAddress->setCity($order->getDpdCity());
        $shippingAddress->setPostcode($order->getDpdZipcode());
        $shippingAddress->setCountryId($order->getDpdCountry());
        $shippingAddress->setCompany('');

        if($this->scopeConfig->getValue('dpdshipping/account_settings/picqer_mode')) {
            $shippingAddress->setFirstname($order->getBillingAddress()->getFirstname());
            $shippingAddress->setLastname($order->getBillingAddress()->getLastname());
            $shippingAddress->setCompany('DPD ParcelShop: ' . $order->getDpdCompany());
        }

        try {
            $telephoneAttribute = $this->attributeRepository->get(AddressMetadataInterface::ENTITY_TYPE_ADDRESS, OrderAddressInterface::TELEPHONE);
            // Check if the attribute is required as otherwise the order can't be placed
            if (!$telephoneAttribute->getIsRequired()) {
                // empty this otherwise you'd get customer data and DPD parcelshop data mixed up
                $shippingAddress->setTelephone('');
            }
        } catch (NoSuchEntityException|LocalizedException $e) {
            // Don't allow thrown exceptions to interrupt the customer's order
        }
    }
}
