# YesWiki's Api Documentation 

## Getting started 

The api access is closed by default, you need to define a parameter as specified below to open it.

### Public API scenario

**⚠️ Be careful, in a public api, everybody can access to all data !**

In `wakka.config.php`, add the parameter :

```php
'api_allowed_keys' => ['public' => true],
```

Now available routes will be shown on url <https://mywiki.url/?api>


### Private API scenario

In `wakka.config.php`, set the parameter :
```php
 'api_allowed_keys' =>
  [
    'custom-client-id' => 'NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc=',
  ],
```

_The keyname `custom-client-id` is not important and only useful to identificate the user of the token._

### Access with curl

Define the http header with the token alike the config file (be careful to the spaces) :point_down: 

```
Authorization: Bearer NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc=
```
Example using curl
```bash
curl https://mywiki.url/?api/hello/IT%20WORKS!!
	-H "Accept: application/json"
    -H "Authorization: Bearer NoHloX4xVNZQNyhpvwwbHczS4DPTSbqZbm6rIJ9VFRc="
```

## Principes (coding standards)

In yeswiki's core code, the main declaration is in file : 
`includes/controllers/ApiController.php`.
This `controllers/ApiController.php` can be defined in any extension (in the tools folder, example `tools/helloworld/controllers/ApiController.php`)

We are using Symfony annotations to **declare new routes** for the API.
```php
/**
 * @Route("/api/user/{userId}")
 */
public function getUser($userId)
{ ...}
```
To show your routes in the main `https://mywiki.url/?api` page, you need to declare a getDocumentation() method in your custom ApiController class that will return the html to display on the page.
```php
    /**
     * Display helloworld api documentation
     *
     * @return void
     */
    public function getDocumentation()
    {
        # your code
        $output = '<h2>Extension HelloWorld</h2>';
        $output .= '<div>Your doc</div>';
        return $output;
    }
```