## Paymentez - Magento 2

### Instalación

Se require agregar el repositorio del código a tu archivo `composer.json`

```js
...
	"respositories": [
		{
          "type": "vcs",
          "url": "https://cristian-paymentez@bitbucket.org/cristian-paymentez/paymentez-magento2.git"
        }
	]
...
```

También se puede configurar vía shell:

`composer config repositories.paymentez vcs https://cristian-paymentez@bitbucket.org/cristian-paymentez/paymentez-magento2.git`

Posterior a esto se requiere instalar el módulo:

`composer require paymentez/magento2`

Al finalizar la ejecución de composer se necesita ejecutar los siguientes comandos de Magento para que reconozca el módulo de Paymentez.

```bash
# Actuliazar la inyección de dependencias
php bin/magento setup:di:compile

# Actulizar el registro de los módulos en Magento
php bin/magento setup:upgrade

# Este último comando es opcional para ambientes productivos
php bin/magento setup:static-content:deploy
```

Una vez terminado la ejecución de los últimos comandos podrás acceder a la configuración del módulo de Paymentez en el administrado de Magento `Stores > Configuration > Sales > Payment Methods`
