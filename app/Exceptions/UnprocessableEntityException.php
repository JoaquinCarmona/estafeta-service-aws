<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UnprocessableEntityException extends UnprocessableEntityHttpException
{
	protected $params;

    /**
     * Constructor.
     *
	 * @param array      $params   An array that contains errors by parameters
     */
    public function __construct($params = [])
    {
        parent::__construct();

		$this->params = $params;
    }

	/**
	 * Returns the params
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}
}
