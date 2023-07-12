<?php

namespace Ismaelet\Interface\Instagram\Graph;

use DB;
use HttpResponse;

class Api {

	private static $clientId = '586804842971911';
	private static $clientSecret = '9cc4fc9f496973f65727df75f2523c19';

	/*
	* Set the redirect URI
	*/
	private static function getRedirectUri() {
		return "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	}

	/*
	* Redirect to the Instagram page to request the temporary code
	*/
	public static function requestToken() {
		$queryParameters = [
			'client_id' => self::$clientId,
			'client_secret' => self::$clientSecret,
			'redirect_uri' => self::getRedirectUri(),
			'response_type' => 'code',
			'scope' => 'user_profile',
		];

		$query =  http_build_query($queryParameters);

		header('Location: https://www.instagram.com/oauth/authorize/?' . $query);
	}

	/*
	* Exchange the temporary code for a short lived token
	*/
	public static function exchangeCode($temporaryCode) {
		$endpoint = 'https://api.instagram.com/oauth/access_token';

		$queryParameters = [
			'client_id' => self::$clientId,
			'client_secret' => self::$clientSecret,
			'code' => $temporaryCode,
			'grant_type' => 'authorization_code',
			'redirect_uri' => self::getRedirectUri(),
		];

		$response = (new HttpResponse('POST', $endpoint, $queryParameters))->json();

		switch (true) {
			case isset($response['access_token']):
				return $response['access_token'];
			case isset($response['error_type']):
				self::requestToken();
			default:
				debug($response);
		}
	}

	/*
	* Exchange a short lived code for a long lived token
	*/
	public static function exchangeShortLivedToken($shortLivedToken) {
		$endpoint = 'https://graph.instagram.com/access_token';

		$queryFields = [
			'grant_type' => 'ig_exchange_token',
			'client_secret' => self::$clientSecret,
			'access_token' => $shortLivedToken,
		];

		$response = (new HttpResponse('GET', $endpoint, $queryFields))->json();

		switch (true) {
			case isset($response['access_token']):
				return $response['access_token'];
			default:
				debug($response);
		}
	}

	public static function refreshToken($oldToken) {
		$endpoint = 'https://graph.instagram.com/refresh_access_token';
		$queryFields = [
			'grant_type' => 'ig_refresh_token',
			'access_token' => $oldToken
		];

		$response = (new HttpResponse('GET', $endpoint, $queryFields))->json();

		if (!isset($response['error']) && isset($response['access_token'])) {
			$newToken = $response['access_token'];

			DB::update('config SET value = ? WHERE name = "instagramToken"', [$newToken]);
			DB::update('config SET value = NOW() WHERE name = "instagramTokenTime"');

			return $newToken;
		} else {
			error_log('Instagram API error on token refresh: ' . print_r($response['error'], true));

			return null;
		}
	}
}
