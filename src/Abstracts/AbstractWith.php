<?php

declare(strict_types=1);

namespace Devcraft\Abstracts;

use BadMethodCallException;
use Devcraft\Runtime\WithHandler;

abstract class AbstractWith {

	public function __call(string $methodName, array $arguments): mixed {
		if(WithHandler::handles($this, $methodName)) {
			return WithHandler::call($this, $methodName, $arguments);
		}

		throw new BadMethodCallException(sprintf(
			'Call to undefined method %s::%s()',
			$this::class,
			$methodName,
		));
	}

}
