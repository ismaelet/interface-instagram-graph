<?php

namespace Ismaelet\Interface\Instagram\Graph;

use DB;
use HttpResponse;

class Api {

	private const DEBUGGING = false;

	private static $clientId = '586804842971911';
	private static $clientSecret = '9cc4fc9f496973f65727df75f2523c19';

	/*
	* Set the redirect URI
	*/
	private static function calcRedirectUri() {
		return "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	}

	/*
	* Redirect to the Instagram page to request the temporary code
	*/
	public static function requestToken() {
		$redirectUri = self::calcRedirectUri();

		header('Location: https://www.instagram.com/oauth/authorize/?client_id=' . self::$clientId . '&client_secret=' . self::$clientSecret . '&redirect_uri=' . $redirectUri . '&response_type=code&scope=user_profile');
	}

	/*
	* Exchange the temporary code for a token
	*/
	public static function exchangeCode($temporaryCode) {
		$endpoint = 'https://api.instagram.com/oauth/access_token';
		$redirectUri = self::calcRedirectUri();

		$queryFields = [
			'client_id' => self::$clientId,
			'client_secret' => self::$clientSecret,
			'code' => $temporaryCode,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $redirectUri,
		];

		$response = new HttpResponse('POST', $endpoint, $queryFields);
		$response = $response->json();

		if (self::DEBUGGING) debug($response);

		if (isset($response['access_token'])) return $response['access_token'];
		else if (isset($response['error_type'])) self::requestToken();
		else print_r($response);
	}

	public static function refreshToken($oldToken) {
		$endpoint = 'https://graph.instagram.com/refresh_access_token';
		$queryFields = [
			'grant_type' => 'ig_refresh_token',
			'access_token' => $oldToken
		];

		$response = new HttpResponse('GET', $endpoint, $queryFields);
		$response = $response->json();

		if (self::DEBUGGING) debug($response);

		if (!isset($response['error'])) {
			$newToken = $response['access_token'];

			DB::update('config SET value=? WHERE name="instagramToken"', [$newToken]);
			DB::update('config SET value=NOW() WHERE name="instagramTokenTime"');

			return $newToken;
		} else {
			error_log('Instagram API error on token refresh: ' . print_r($response['error'], true));

			return null;
		}
	}
}
