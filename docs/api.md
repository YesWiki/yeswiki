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
 * @Route("/api/users/{userId}")
 */
public function getUser($userId)
{ ...}
```
To show your routes in the main `https://mywiki.url/?api` page, you need to declare a getDocumentation() method in your custom ApiController class that will return the html to display on the page.
Be careful to the namespace used in your file `YesWiki\ExtensionName\controller`
```php
    /**
     * Display helloworld api documentation
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Extension Hello World</h2>';

        $urlHello = $this->wiki->Href('', 'api/hello/test');
        $urlHelloTest = $this->wiki->Href('', 'api/hello/{test}');
        $output .= 'The following code :<br />';
        $output .= 'GET <code>'.$urlHelloTest.'</code><br />';
        $output .= 'gives :<br />';
        $output .= '<code>test</code><br />Example : <br />';
        $output .= 'GET <code><a href="'.$urlHello.'">'.$urlHello.'</a></code><br />';

        return $output;
    }
```

### Open a public route in private API scenario

It is possible to open a **public route** even in private API scenario.

In the file of the route, change the method's annotation from :
```php
/**
 * @Route("/api")
 */
```
to
```php
/**
 * @Route("/api",options={"acl":{"public"}})
 */
```

This route will be accessible to everyone without authorization bearer.

**To open a route to specific group _@mygroup_ without bearer**, change the method's annotation from :
```php
/**
 * @Route("/api")
 */
```
to
```php
/**
 * @Route("/api",options={"acl":{"public","@mygroup"}})
 */
```

This route will be accessible only to members of this group without authorization bearer.
_The cookies or $_POST for authentification the concerned user should be sent in the request._