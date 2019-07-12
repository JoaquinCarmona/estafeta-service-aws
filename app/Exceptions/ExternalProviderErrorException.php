<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ExternalProviderErrorException extends HttpException
{
	protected $subtype;
	protected $objectId;

	/**
	 * Constructor
	 *
	 * @param string $message
	 * @param string $subtype
	 */
    public function __construct($message, $subtype, $objectId)
    {
		parent::__construct(503, $message, null, array(), 0);

		$this->subtype = $subtype;
		$this->objectId = $objectId;
    }


	/**
	 * Returns the error subtype
	 *
	 * @return string
	 */
	public function getSubtype()
	{
		return $this->subtype;
	}

	/**
	 * Returns the object id
	 *
	 * @return integer
	 */
	public function getObjectId()
	{
		return $this->objectId;
	}
}
