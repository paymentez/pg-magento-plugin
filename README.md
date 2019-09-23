# Paymentez Magento module

This module is a solution that allows Magento users to easily process credit card payments.

## Installation

#### 1. Add to composer file 

First of all you need add our repository in your `composer.json` file.

**Option A: Edit composer file**:

If you can add the repository directly in file using some editor file. See example below:

```
...
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/paymentez/pg-magento-plugin.git"
        }
    ]
...
```

**Option B: Composer config**:

Or you can use a composer command to add it.

`composer config repositories.paymentez vcs https://github.com/paymentez/pg-magento-plugin.git`


#### 2. Download and installation

**1. Now execute this command for install our package:**

Install the latest version.  `composer require paymentez/magento2`

Install a specific version.  `composer require paymentez/magento2:1.1.9`

Once the installation is finished continue with the next commands in your bash terminal.

**2. Update dependency injection:**

`php bin/magento setup:di:compile`


**3. Update modules registry:**

`php bin/magento setup:upgrade`


**Optional.- This command is optional for production environments:**

`php bin/magento setup:static-content:deploy`


Now you can see the Paymentez settings in this path `Stores > Configuration > Sales > Payment Methods` on your Magento admin dashboard.


## Maintenance
If you need update the plugin to latest version execute step 2 of Installation [here](#2.-download-and-installation)


## Fraud notifications via webhook

When Paymentez detect a possible fraud we use notifications through webhook for notify to Magento Admin for make an update the order state and status.

The webhook path by default is:

`/V1/paymentez/notification/listener`

So, the possible fraud notifications can be send to:

`https://magentodomain.com/V1/paymentez/notification/listener`



