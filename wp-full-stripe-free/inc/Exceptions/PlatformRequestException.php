<?php

namespace ThemeIsle\WPFullPay\Exceptions;

/**
 * A failed request to the WP Full Pay platform API, with what the platform answered.
 *
 * It stays a UserFriendlyException, so callers that show the message keep working.
 */
class PlatformRequestException extends UserFriendlyException {

	/** Error codes the platform answers when it refuses this site's credential. */
	const CREDENTIAL_CODES = [ 'credential_missing', 'credential_invalid', 'credential_account_mismatch' ];

	/** @var int HTTP status, or 0 when the request never got an answer. */
	private $httpStatus;

	/** @var string The platform's error code, or '' when it gave none. */
	private $errorCode;

	/** @var int Seconds the platform asked to wait before trying again, or 0 when it didn't say. */
	private $retryAfter;

	/**
	 * @param string          $message
	 * @param int             $httpStatus
	 * @param string          $errorCode
	 * @param \Throwable|null $previous
	 * @param int             $retryAfter
	 */
	public function __construct( $message, int $httpStatus = 0, string $errorCode = '', ?\Throwable $previous = null, int $retryAfter = 0 ) {
		parent::__construct( $message, 0, $previous );
		$this->httpStatus = $httpStatus;
		$this->errorCode = $errorCode;
		$this->retryAfter = $retryAfter;
	}

	public function getRetryAfter(): int {
		return $this->retryAfter;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	/**
	 * Whether the platform refused this site's credential, rather than the request itself.
	 */
	public function isCredentialRefusal(): bool {
		return in_array( $this->errorCode, self::CREDENTIAL_CODES, true );
	}

	/**
	 * Whether the outcome is unknown: no answer, an answer that isn't a client error the platform meant
	 * (a redirect, an unreadable success, a timeout or throttling before the platform ran), or a server
	 * error. The request may have taken effect, or may still succeed when sent again.
	 */
	public function isAmbiguous(): bool {
		return $this->httpStatus < 400 || $this->httpStatus >= 500 || 408 === $this->httpStatus || 429 === $this->httpStatus;
	}
}
