Sage Pay Now - OsCommerce Payment Gateway Module
================================================

Revision 1.1.2

Introduction
------------

Sage Pay South Africa's Pay Now third party gateway integration for osCommerce. The module gives you to access the Pay Now gateway which in turns lets you process online shopping cart transactions using osCommerce. VISA and MasterCard are supported.

Installation Instructions
-------------------------

Download the files from Github and copy them to your catalog directory:
* https://github.com/SagePay/PayNow-OsCommerce/archive/master.zip

The four files needed are:

* /ext/modules/payment/sagepaynow.php
* /includes/languages/english/modules/payment/sagepaynow.php
* /includes/modules/payment/sagepaynow.php
* /includes/modules/payment/sagepaynow_common.inc

They should be copied to your catalog folders, e.g.:

/your_catalog/ext/modules/payment/sagepaynow.php
/your_catalog/includes/languages/english/modules/payment/sagepaynow.php
/your_catalog/includes/modules/payment/sagepaynow.php
/your_catalog/includes/modules/payment/sagepaynow_common.inc

Configuration
-------------

Prerequisites:

You will need:
* Sage Pay Now login credentials
* Sage Pay Now Service key
* OpenCart admin login credentials

A. Sage Pay Now Gateway Server Configuration Steps:

1. Log into your Sage Pay Now Gateway Server configuration page:
	https://merchant.sagepay.co.za/SiteLogin.aspx
2. Type in your Sage Pay Username, Password, and PIN
2. Click on Account Profile
3. Click Sage Connect
4. Click on Pay Now
5. Click "Active:"
6. Type in your Email address
7. Click "Allow credit card payments:"

8. The Accept and Decline URLs should both be:
	http://www.your_website.co.za/ext/modules/payment/sagepaynow/sagepaynow.php

9. It is highly recommended that you "Make test mode active:" while you are still testing your site.

B. osCommerce Steps:

1. Log into osCommerce as administrator
2. Navigate to Modules / Payment
3. Click '+ Install Module' on the right hand side
4. Type in your Sage Pay Now Service key
5. If you would like to enable debugging, type an email address and click 'Enable Debugging'
6. Click 'Save'

You are now ready to transact. Remember to turn of "Make test mode active:" when you are ready to go live.

Here is a screenshot of what the osCommerce settings screen for the Sage Pay Now configuration:
![alt tag](http://oscommerce.gatewaymodules.com/oscommerce_screenshot1.png)

Demo Site
---------
There is a demo site if you want to see osCommerce and the Sage Pay Now gateway in action:
http://oscommerce.gatewaymodules.com

Revision History
----------------

* 18 Aug 2014/1.1.2 Updated P3 to be more descriptive and added M6 to equal P3
                    Added more descriptive information in README.md with regards to where files should be installed
* 10 May 2014/1.1.1	Improved README.md file with a screenshot of settings
* 29 Mar 2014/1.1.0	Documentation review
* 5 Mar 2014/1.0.0	First version

Tested with OsCommerce version 2.3.3.4

Feedback, issues & feature requests
-----------------------------------
If you have any feedback please contact Sage Pay South Africa or log an issue on GitHub

