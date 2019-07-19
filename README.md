# Paymentez Magento module
===================

This module is a solution that allows Magento users to easily process credit card payments.

## Installation

First of all you need add our repository in your `composer.json` file.

See example below


```js
...
	"respositories": [
		{
          "type": "vcs",
          "url": "https://github.com/paymentez/pg-magento-plugin.git"
        }
	]
...
```

**Straightforward path**:

`composer config repositories.paymentez vcs https://github.com/paymentez/pg-magento-plugin.git`

Now execute this command for install our package:

`composer require paymentez/magento2`

Once the installation is finished execute the next commands in your bash terminal.

```bash
# Update dependency injection
php bin/magento setup:di:compile

# Update module registry
php bin/magento setup:upgrade

# This command is optional for production environments
php bin/magento setup:static-content:deploy
```

Now you can see the Paymentez settings in this path `Stores > Configuration > Sales > Payment Methods` on your Magento admin dashboard.

