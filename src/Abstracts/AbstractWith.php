<?php

declare(strict_types=1);

namespace Devcraft\Abstracts;

use Lombok\Helper;
use Devcraft\Runtime\WithHandler;

abstract class AbstractWith extends Helper {

	public function __call(string $methodName, array $arguments): mixed {
		if(WithHandler::handles($this, $methodName)) {
			return WithHandler::call($this, $methodName, $arguments);
		}

		return parent::__call($methodName, $arguments);
	}

}
