------------------------------------------------------------
        PipraPay Payment Module for SMM Matrix
------------------------------------------------------------

Integrating the PipraPay payment module into your SMM Matrix platform is simple and seamless. Follow the instructions below to complete the setup.

------------------------------------------------------------
📁 STEP 1: Create the PipraPay Folder
------------------------------------------------------------

Create the following directory:

> app/Services/Gateway/**piprapay**

Ensure the folder is named exactly: **piprapay** (all lowercase)

------------------------------------------------------------
📄 STEP 2: Upload Payment.php
------------------------------------------------------------

Upload the file:  
> **Payment.php**

To the folder:  
> app/Services/Gateway/**piprapay**

------------------------------------------------------------
🗄️ STEP 3: Import the Database Structure
------------------------------------------------------------

1. Open **PhpMyAdmin**
2. Select your **SMM Panel Database**
3. Import the provided file:  
> **database.sql**

This will insert all required configurations and database tables for PipraPay to work correctly.

------------------------------------------------------------
🔐 STEP 4: Exclude Webhook Route
------------------------------------------------------------

1. Open the file:  
> `app/Http/Middleware/VerifyCsrfToken.php`

2. Add the following line inside the `$except` array:

```php
'payment/piprapay/*',

Your updated $except array will look like this:

protected $except = [
    '*save-token*',
    '*sort-payment-methods*',
    '*admin/upload/ck/image*',
    'payment/piprapay/*', // <-- Added for PipraPay
];
