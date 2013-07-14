<?php
/**
*
* @package auth
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Uri\Uri;

/**
* OAuth authentication provider for phpBB3
*
* @package auth
*/
class phpbb_auth_provider_oauth extends phpbb_auth_provider_base
{
	/**
	* Database driver
	*
	* @var phpbb_db_driver
	*/
	protected $db;

	/**
	* phpBB config
	*
	* @var phpbb_config
	*/
	protected $config;

	/**
	* phpBB request object
	*
	* @var phpbb_request
	*/
	protected $request;

	/**
	* phpBB user
	*
	* @var phpbb_user
	*/
	protected $user;

	/**
	* OAuth token table
	*
	* @var string
	*/
	protected $auth_provider_oauth_table;

	/**
	* Cached services once they has been created
	*
	* @var array Contains \OAuth\Common\Service\ServiceInterface or null
	*/
	protected $services;

	/**
	* Cached current uri object
	*
	* @var \OAuth\Common\Http\Uri\UriInterface|null
	*/
	protected $current_uri;

	/**
	* OAuth Authentication Constructor
	*
	* @param	phpbb_db_driver $db
	* @param	phpbb_config 	$config
	* @param	phpbb_request 	$request
	* @param	phpbb_user 		$user
	* @param	string			$auth_provider_oauth_table
	*/
	public function __construct(phpbb_db_driver $db, phpbb_config $config, phpbb_request $request, phpbb_user $user, $auth_provider_oauth_table)
	{
		$this->db = $db;
		$this->config = $config;
		$this->request = $request;
		$this->user = $user;
		$this->auth_provider_oauth_table = $auth_provider_oauth_table;
		$this->services = array();
	}

	/**
	* {@inheritdoc}
	*/
	public function login($username, $password)
	{
		// Requst the name of the OAuth service
		$service_name = $this->request->variable('oauth_service', '', false, phpbb_request_interface::POST);
		if ($service_name === '')
		{
			return array(
				'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
				// TODO: change error message
				'error_msg'		=> 'LOGIN_ERROR_EXTERNAL_AUTH_APACHE',
				'user_row'		=> array('user_id' => ANONYMOUS),
			);
		}

		// Get the service credentials for the given service
		$service_credentials = $this->get_credentials($service_name);

		// Check that the service has settings
		if ($service_credentials['key'] == false || $service_credentials['secret'] == false)
		{
			return array(
				'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
				// TODO: change error message
				'error_msg'		=> 'LOGIN_ERROR_EXTERNAL_AUTH_APACHE',
				'user_row'		=> array('user_id' => ANONYMOUS),
			);
		}

		$storage = new phpbb_auth_provider_oauth_token_storage($this->db, $this->user, $service_name, $this->auth_provider_oauth_table);
		$service = $this->get_service($service_name, $storage, $service_credentials, $this->get_scopes($service_name));

		if ($this->request->is_set('code', phpbb_request_interface::GET))
		{
			// This was a callback request from the service provider
			$service->requestAccessToken( $_GET['code'] );

			// Send a request with it
			$path = $this->get_path($service_name);
			if ($path)
			{
				$result = json_decode( $service->request($path), true );
			}

			// Perform authentication
		} else {
			$url = $service->getAuthorizationUri();
			// TODO: modify $url for the appropriate return points
			header('Location: ' . $url);
		}
	}

	/**
	* Returns an array containing the service credentials belonging to requested
	* service.
	*
	* @param	string	$service_name	The name of the service
	* @return	array	An array containing the 'key' and the 'secret' of the
	*					service in the form:
	*						array(
	*							'key'		=> string
	*							'secret'	=> string
	*						)
	*/
	protected function get_service_credentials($service_name)
	{
		return array(
			'key'		=> $this->config['auth_oauth_' . $service_name . '_key'],
			'secret'	=> $this->config['auth_oauth_' . $service_name . '_secret'],
		);
	}

	/**
	* Returns the cached current_uri object or creates and caches it if it is
	* not already created
	*
	* @return	\OAuth\Common\Http\Uri\UriInterface
	*/
	protected function get_current_uri()
	{
		if ($this->current_uri)
		{
			return $this->current_uri;
		}

		$uri_factory = new \OAuth\Common\Http\Uri\UriFactory();
		$current_uri = $uri_factory->createFromSuperGlobalArray($this->request->get_super_global(phpbb_request_interface::SERVER));
		$current_uri->setQuery('');

		$this->current_uri = $current_uri;
		return $current_uri;
	}

	/**
	* Returns the cached service object or creates a new one
	*
	* @param	string	$service_name			The name of the service
	* @param	phpbb_auth_oauth_token_storage $storage
	* @param	array	$service_credentials	{@see phpbb_auth_provider_oauth::get_service_credentials}
	* @param	array	$scope					The scope of the request against
	*											the api.
	* @return	\OAuth\Common\Service\ServiceInterface
	*/
	protected function get_service($service_name, phpbb_auth_oauth_token_storage $storage, array $service_credentials, array $scopes = array())
	{
		if ($this->services[$service_name])
		{
			return $this->services[$service_name];
		}

		$current_uri = $this->get_current_uri();

		// Setup the credentials for the requests
		$credentials = new Credentials(
			$service_credentials['key'],
			$service_credentials['secret'],
			$current_uri->getAbsoluteUri()
		);

		$service_factory = new \OAuth\ServiceFactory();
		$this->service[$service_name] = $service_factory->createService($service_name, $credentials, $storage, $scopes);

		return $this->service[$service_name];
	}

	/**
	* Returns the scopes of the service required for authentication
	*
	* @param	string	$service_name
	* @return	array	An array of the scopes required from the service
	*/
	protected function get_scopes($service_name)
	{
		$scopes = array();

		switch ($service_name)
		{
			case 'GitHub':
				$scopes[] = 'user';
				break;
			case 'google':
				$scopes[] = 'userinfo_email';
				$scopes[] = 'userinfo_profile';
				break;
			case 'instagram':
			case 'microsoft':
				$scopes[] = 'basic';
				break;
			case 'linkedin':
				$scopes[] = 'r_basicprofile';
				break;
		}

		return $scopes;
	}

	/**
	* Returns the path desired of the service
	*
	* @param	string	$service_name
	* @return	string|UriInterface|null	A null return means do not
	*										request additional information.
	*/
	protected function get_path($service_name)
	{
		switch ($service_name)
		{
			case 'bitly':
			case 'tumblr':
				$path = 'user/info';
				break;
			case 'box':
				$path = '/users/me';
				break;
			case 'facebook':
				$path = '/me';
				break;
			case 'FitBit':
				$path = 'user/-/profile.json';
				break;
			case 'foursquare':
			case 'instagram':
				$path = 'users/self';
				break;
			case 'GitHub':
				$path = 'user/emails';
				break;
			case 'google':
				$path = 'https://www.googleapis.com/oauth2/v1/userinfo';
				break;
			case 'linkedin':
				$path = '/people/~?format=json';
				break;
			case 'soundCloud':
				$path = 'me.json';
				break;
			case 'twitter':
				$path = 'account/verify_credentials.json';
				break;
			default:
				$path = null;
				break;
		}

		return $path;
	}
}
