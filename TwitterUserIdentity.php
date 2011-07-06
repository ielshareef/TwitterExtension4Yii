<?php
/*
 * This product includes software developed by
 * Ismail Elshareef (http://about.me/ismailelshareef)
 * under Apache 2.0 License (http://www.apache.org/licenses/LICENSE-2.0.html).
 *
 */
class TwitterUserIdentity extends TwitterComponent implements IUserIdentity {

    /**
     * @var string OAuth consumer key.
     */
    public $key = '';
    /**
     * @var string OAuth consumer secret.
     */
    public $secret = '';
	/**
     * @var bool Is the user authenticated?
     */
    private $_authenticated=false;

    public function __construct($key, $secret) {
		if (isset($key) && isset($secret)) {
			$this->key = $key;
			$this->secret = $secret;
		} else {
			throw new TwitterException("Tokens are incorrect or missing.");
		}
    }

    public function getIsAuthenticated() {
        return $this->_authenticated;
    }

    public function getId() {
        return Yii::app()->session['access_token']['user_id'];
    }

    public function getName() {
        return Yii::app()->session['access_token']['screen_name'];
    }

    public function getPersistentStates() {
    }

    public function authenticate() {
		$session=Yii::app()->session;
		$tmhOAuth = new tmhOAuth(array(
		  'consumer_key'    => $this->key,
		  'consumer_secret' => $this->secret,
		));
		$here = Yii::app()->request->hostInfo . Yii::app()->request->url;
		
		// already got some credentials stored?
		if (isset($session['access_token'])) {
			$tmhOAuth->config['user_token']  = $session['access_token']['oauth_token'];
		  	$tmhOAuth->config['user_secret'] = $session['access_token']['oauth_token_secret'];

		  	$tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
		  	$session['twitter_user'] = json_decode($tmhOAuth->response['response']);
			$this->_authenticated = true;

		// we're being called back by Twitter
		} elseif (isset($_REQUEST['oauth_verifier'])) {
		  	$tmhOAuth->config['user_token']  = $session['oauth']['oauth_token'];
		  	$tmhOAuth->config['user_secret'] = $session['oauth']['oauth_token_secret'];

		  	$tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
		    	'oauth_verifier' => $_REQUEST['oauth_verifier']
		  	));
		  	$session['access_token'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		  	unset($session['oauth']);
		  	Yii::app()->request->redirect($here);

		// start the OAuth dance
		} else {
			$callback = $here;
		  	$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/request_token', ''), array(
		    	'oauth_callback' => $callback
		  	));

		  	if ($code == 200) {
		    	$session['oauth'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		    	$method = 'authenticate';
				$url = $tmhOAuth->url("oauth/{$method}", '') . "?oauth_token={$session['oauth']['oauth_token']}";
		    	Yii::app()->request->redirect($url);
				$this->_authenticated = true;
		  	} else {
		    	// error
		    	throw new TwitterException(htmlentities($tmhOAuth->response['response']));
		  	}
		}
		return $this->_authenticated;
	}
}
?>
