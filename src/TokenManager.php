<?php

namespace MediaWiki\CheckUser;

use Firebase\JWT\JWT;
use MediaWiki\User\UserIdentity;

class TokenManager {
	/** @var string */
	private const SIGNING_ALGO = 'HS256';

	/** @var string|null */
	private $encryptionAlgorithm;

	/** @var string */
	private $wikiId;

	/** @var string */
	private $secret;

	/**
	 * @param string $wikiId
	 * @param string $secret
	 */
	public function __construct(
		string $wikiId,
		string $secret
	) {
		if ( $secret === '' ) {
			throw new \Exception(
				'CheckUser Token Manager requires $wgSecretKey to be set.'
			);
		}
		$this->wikiId = $wikiId;
		$this->secret = $secret;
	}

	/**
	 * Get data from Context.
	 *
	 * @param \IContextSource $context
	 * @return array
	 */
	public function getDataFromContext( \IContextSource $context ) : array {
		$token = $context->getRequest()->getVal( 'token' );

		if ( empty( $token ) ) {
			return [];
		}

		try {
			return $this->decode( $context->getUser(), $token );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Creates a token
	 *
	 * @param UserIdentity $currentUser
	 * @param array $data
	 * @return string
	 */
	public function encode( UserIdentity $currentUser, array $data ) : string {
		return JWT::encode(
			[
				// Issuer https://tools.ietf.org/html/rfc7519#section-4.1.1
				'iss' => $this->wikiId,
				// Subject https://tools.ietf.org/html/rfc7519#section-4.1.2
				'sub' => $currentUser->getName(),
				// Expiration Time https://tools.ietf.org/html/rfc7519#section-4.1.4
				'exp' => \MWTimestamp::time() + 86400, // 24 hours from now
				// Encrypt the form data to pevent it from being leaked.
				'data' => $this->encrypt(
					$data,
					$this->wikiId . $currentUser->getName()
				),
			],
			$this->secret,
			self::SIGNING_ALGO
		);
	}

	/**
	 * Encrypt private data.
	 *
	 * @param mixed $input
	 * @param string $seed
	 * @return string
	 */
	private function encrypt( $input, string $seed ) : string {
		return openssl_encrypt(
			\FormatJson::encode( $input ),
			$this->getEncryptionAlgorithm(),
			$this->secret,
			0,
			$this->getInitializationVector( $seed )
		);
	}

	/**
	 * Decode the JWT and return the targets.
	 *
	 * @param UserIdentity $currentUser
	 * @param string $token
	 * @return array
	 */
	public function decode( UserIdentity $currentUser, string $token ) : array {
		$payload = JWT::decode( $token, $this->secret, [ self::SIGNING_ALGO ] );

		if ( $payload->iss !== $this->wikiId ) {
			throw new \Exception( 'Invalid Token' );
		}

		if ( !$currentUser->equals( \User::newFromName( $payload->sub ) ) ) {
			throw new \Exception( 'Invalid Token' );
		}

		return $this->decrypt(
			$payload->data,
			$this->wikiId . $currentUser->getName()
		);
	}

	/**
	 * Decrypt private data.
	 *
	 * @param string $input
	 * @param string $seed
	 * @return array
	 */
	private function decrypt( string $input, string $seed ) : array {
		$decrypted = openssl_decrypt(
			$input,
			$this->getEncryptionAlgorithm(),
			$this->secret,
			0,
			$this->getInitializationVector( $seed )
		);

		if ( $decrypted === false ) {
			throw new \Exception( 'Decryption Failed' );
		}

		return \FormatJson::parse( $decrypted, \FormatJson::FORCE_ASSOC )->getValue();
	}

	/**
	 * Get the Initialization Vector.
	 *
	 * This must be consistent between encryption and decryption
	 * and must be no more than 16 bytes in length.
	 *
	 * @param string $seed
	 * @return string
	 */
	private function getInitializationVector( string $seed ) : string {
		return hash_hmac( 'md5', $seed, $this->secret, true );
	}

	/**
	 * Decide what type of encryption to use, based on system capabilities.
	 *
	 * @see \MediaWiki\Session\Session::getEncryptionAlgorithm()
	 *
	 * @return string
	 */
	private function getEncryptionAlgorithm() : string {
		if ( !$this->encryptionAlgorithm ) {
			$methods = openssl_get_cipher_methods();
			if ( in_array( 'aes-256-ctr', $methods, true ) ) {
				$this->encryptionAlgorithm = 'aes-256-ctr';
			} elseif ( in_array( 'aes-256-cbc', $methods, true ) ) {
				$this->encryptionAlgorithm = 'aes-256-cbc';
			} else {
				throw new \Exception( 'No valid cipher method found with openssl_get_cipher_methods()' );
			}
		}

		return $this->encryptionAlgorithm;
	}
}
