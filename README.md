# Paymentez Magento module

This module is a solution that allows Magento users to easily process credit card payments.

## Download and Installation

**1. Execute this command for install our package:**

Install the latest version.  `composer require paymentez/magento2`

Install a specific version.  `composer require paymentez/magento2:1.2`

Once the installation is finished continue with the next commands in your bash terminal.


**2. Update modules registry:**

`php bin/magento setup:upgrade`


**3. Update dependency injection:**

`php bin/magento setup:di:compile`


**Optional.- This command is optional for production environments:**

`php bin/magento setup:static-content:deploy`


Now you can see the Paymentez settings in this path `Stores > Configuration > Sales > Payment Methods` on your Magento admin dashboard.


## Maintenance
If you need update the plugin to latest version execute: `composer update paymentez/magento2`

## Fraud notifications via webhook

When Paymentez detect a possible fraud we use notifications through webhook for notify to Magento Admin for make an update the order state and status.

The webhook path by default is:

`/V1/paymentez/notification/listener`

So, the possible fraud notifications can be send to:

`https://magentodomain.com/V1/paymentez/notification/listener`



