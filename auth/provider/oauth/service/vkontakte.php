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

/**
* VKontakte OAuth service
*
* @package auth
*/
class phpbb_auth_provider_oauth_service_vkontakte extends phpbb_auth_provider_oauth_service_base
{
	/**
	* phpBB config
	*
	* @var phpbb_config
	*/
	protected $config;

	/**
	* Constructor
	*
	* @param	phpbb_config 	$config
	*/
	public function __construct(phpbb_config $config)
	{
		$this->config = $config;
	}

	/**
	* {@inheritdoc}
	*/
	public function get_service_credentials()
	{
		return array(
			'key'		=> $this->config['auth_oauth_vkontakte_key'],
			'secret'	=> $this->config['auth_oauth_vkontakte_secret'],
		);
	}
}
