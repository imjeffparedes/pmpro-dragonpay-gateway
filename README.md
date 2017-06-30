**DragonPay Payment Plugin** 

* Payment integration with Paid Membership Pro
* Add DragonPay in Payment Options plus Multiple Payment Settings for each Membership Levels.
* Version v1.0.0 | By Jeff Paredes

# WordPress Paid Membership Pro Payment Plugin for DragonPay

Add DragonPay in Payment Options plus Multiple Payment Settings for each Membership Levels.

## Getting Started

These instructions will get you a copy of the project up and running on your WordPress Paid Membership Pro Plugin for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

Install Paid Membership Pro. Visit here for more info. https://www.paidmembershipspro.com/step-by-step-guides/pdf-guides/how-to-set-up-paid-memberships-pro/


### Installing

Please follow these step by step series of examples that tell you have to use this plugin.
This steps will install the plugin and can be activated as Production or Testing env.

1. Open your site files and Navigate to this folder "ftp://ftp.yoursite.com/public_html/members/wp-content/plugins/"

2. Create folder  "pmpro-dragonpay-gateway"

3. And copy all these files to this folder "**pmpro-dragonpay-gateway.php**, **classes/*** , **includes/*** , and **services/*** "

4. Go to your **WordPress Portal** an navigate to **Plugins** " https://www.yoursite.com/members/wp-admin/plugins.php "

5. Activate DragonPay Gateway for Paid Memberships Pro Plugin

6. Setup DragonPay Settings. Navigate to **WordPress Dashboard > Membership > Payment Setting** (https://www.yoursite.com/members/wp-admin/admin.php?page=pmpro-paymentsettings). Then choose Dragon Pay as your Payment Setting

7. Fill Up your Merchant details provided by Dragon Pay contacts and Choose your development environment.

8. Create your postback page / Payment Status Page

9. Navigate to  **WordPress Dashboard > Page Creator**. Then Add New Page.

10. Copy content of to create the page "**payment-status.php**". **IMPORTANT!** Replace the page links of the content on TODO area.

11. **IMPORTANT!** Tell your Dragon Pay contacts to redirect the payment postback to this Payment Status page in order to activate the payment. This will update the status of payment on your website.


## Customization

Changed DragonPay Checkout button image on "**classes/class.pmprogateway_dragonpay.php**" line 189

```
#!html

<input type="image" class="pmpro_btn-submit-checkout" value="Check Out with DragonPay &raquo;" width="150px" src="**https://www.dragonpay.ph/wp-content/themes/wp365_theme/img/logo_dragonpay.png**" />
```

## Deployment

Inorder to use DragonPay Test env/merchant account, change Gateway Environment on Payment Setting

## Built With

* [PHP](http://php.net/manual/en/intro-whatis.php) - The programming language used

## Contributing

Please read [CONTRIBUTING.md](https://gist.github.com/PurpleBooth/b24679402957c63ec426) for details on our code of conduct, and the process for submitting pull requests to us.

## Authors

* **Jefferson Paredes** - *Developer* - [imjeffparedes](https://github.com/imjeffparedes/)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* UpcatReview.com
* Sir. Ian Escamos
* Ms. Cons Paita