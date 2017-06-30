**DragonPay Payment Plugin** 

* Payment integration with Paid Membership Pro
* Add DragonPay in Payment Options plus Multiple Payment Settings for each Membership Levels.
* Version v1.0.0 | By Jeff Paredes

# WordPress Paid Membership Pro Payment Plugin for DragonPay

Add DragonPay in Payment Options plus Multiple Payment Settings for each Membership Levels.

## Getting Started

These instructions will get you a copy of the project up and running on your WordPress Paid Membership Pro Plugin for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

Install Paid Membership Pro 
here https://www.paidmembershipspro.com/step-by-step-guides/pdf-guides/how-to-set-up-paid-memberships-pro/


### Installing

Please follow these step by step series of examples that tell you have to use this plugin.
This steps will install the plugin and can be activated as Production or Testing env.

1. Open your site files and Navigate to this folder "ftp://ftp.yoursite.com/public_html/members/wp-content/plugins/"

2. Create folder  "pmpro-dragonpay-gateway"

3. And copy all these files to this folder "pmpro-dragonpay-gateway.php, classes/* , includes/* , and services/* "

4. Go to your WordPress Portal an navigate to Plugins " https://www.yoursite.com/members/wp-admin/plugins.php "

5. Activate DragonPay Gateway for Paid Memberships Pro Plugin

6. Setup DragonPay Settins

6. 1. Navigate to WordPress Dashboard > Membership > Payment Setting (https://www.yoursite.com/members/wp-admin/admin.php?page=pmpro-paymentsettings)
6. 2. Then choose Dragon Pay as your Payment Setting

6. 3. Fill Up your Merchant details provided by Dragon Pay contacts and Choose your development environment.

![alt text](https://raw.githubusercontent.com/imjeffparedes/pmpro-dragonpay-gateway/images/payment-settings.png)

8. Create your postback page / Payment Status Page
8.1. Navigate to  WordPress Dashboard > Page Creator.
8.2. Then Add New Page
8.3. Copy content of to create the page "payment-status.php"
8.4. IMPORTANT! Replace the page links of the content on TODO area.

9. IMPORTANT! Tell your Dragon Pay contacts to redirect the payment postback to this Payment Status page in order to activate the payment. This will update the status of payment on your website.

Here's what it looks like upon payment

![alt text](https://raw.githubusercontent.com/imjeffparedes/pmpro-dragonpay-gateway/images/payment-demo.png)

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