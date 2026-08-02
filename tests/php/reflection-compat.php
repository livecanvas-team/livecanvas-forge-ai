<?php

/**
 * Keep reflection-based tests compatible with PHP 8.0.
 *
 * PHP 8.1 made non-public members invokable through reflection without an
 * explicit accessibility change. The plugin still supports PHP 8.0, so the
 * tests must retain the explicit call while that runtime is in the matrix.
 *
 * @param mixed $reflector ReflectionProperty or ReflectionMethod instance.
 * @return mixed
 */
function lcfa_test_accessible_reflector($reflector) {
	if (PHP_VERSION_ID < 80100) {
		$reflector->setAccessible(true);
	}

	return $reflector;
}

/**
 * @param object|string $class
 */
function lcfa_test_reflection_property($class, string $property): ReflectionProperty {
	return lcfa_test_accessible_reflector(new ReflectionProperty($class, $property));
}

/**
 * @param object|string $class
 */
function lcfa_test_reflection_method($class, string $method): ReflectionMethod {
	return lcfa_test_accessible_reflector(new ReflectionMethod($class, $method));
}
