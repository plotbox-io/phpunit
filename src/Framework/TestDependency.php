<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestDependency
{
    /* var string */
    private $className = '';

    /* var string */
    private $methodName = '';

    /* var boolean */
    private $useShallowClone = false;

    /* var boolean */
    private $useDeepClone = false;

    public static function createFromDependsAnnotation(string $className, string $annotation): self
    {
        // Split clone option and target
        $parts = \explode(' ', \trim($annotation), 2);

        if (\count($parts) === 1) {
            $cloneOption = '';
            $target      = $parts[0];
        } else {
            $cloneOption = $parts[0];
            $target      = $parts[1];
        }

        // Prefix provided class for targets assumed to be in scope
        if ($target !== '' && \strpos($target, '::') === false) {
            $target = $className . '::' . $target;
        }

        return new self($target, null, $cloneOption);
    }

    /**
     * @param array<TestDependency> $dependencies
     *
     * @return array<TestDependency>
     */
    public static function filterInvalid(array $dependencies): array
    {
        return \array_filter($dependencies, function (self $d) {
            return $d->isValid();
        });
    }

    /**
     * @param array<TestDependency> $existing
     * @param array<TestDependency  $additional
     *
     * @return array<TestDependency>
     */
    public static function mergeUnique(array $existing, array $additional): array
    {
        $existingTargets = \array_map(function ($dependency) {
            return $dependency->getTarget();
        }, $existing);

        foreach ($additional as $dependency) {
            if (\array_search($dependency->getTarget(), $existingTargets, true) === false) {
                $existingTargets[]  = $dependency->getTarget();
                $existing[]         = $dependency;
            }
        }

        return $existing;
    }

    /**
     * @param array<TestDependency> $left
     * @param array<TestDependency  $right
     *
     * @return array<TestDependency>
     */
    public static function diff(array $left, array $right): array
    {
        if ($right === []) {
            return $left;
        }

        $diff         = [];
        $rightTargets = \array_map(function ($dependency) {
            return $dependency->getTarget();
        }, $right);

        foreach ($left as $dependency) {
            if (\array_search($dependency->getTarget(), $rightTargets, true) === false) {
                $diff[] = $dependency;
            }
        }

        return $diff;
    }

    public function __construct(string $classOrCallableName, ?string $methodName = null, ?string $option = null)
    {
        if ($classOrCallableName === '') {
            return $this;
        }

        if ($methodName !== null && $methodName !== '') {
            $this->className  = $classOrCallableName;
            $this->methodName = $methodName;
        } elseif (\strpos($classOrCallableName, '::') !== false) {
            [$this->className, $this->methodName] = \explode('::', $classOrCallableName);
        } else {
            $this->className  = $classOrCallableName;
            $this->methodName = 'class';
        }

        if ($option === 'clone') {
            $this->useDeepClone  = true;
        } elseif ($option === 'shallowClone') {
            $this->useShallowClone  = true;
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getTarget();
    }

    public function isValid(): bool
    {
        // Invalid dependencies can be declared and are skipped by the runner
        return $this->className !== '' && $this->methodName !== '';
    }

    public function useShallowClone(): bool
    {
        return $this->useShallowClone;
    }

    public function useDeepClone(): bool
    {
        return $this->useDeepClone;
    }

    public function targetIsClass(): bool
    {
        return $this->methodName === 'class';
    }

    public function getTarget(): string
    {
        return $this->isValid()
            ? $this->className . '::' . $this->methodName
            : '';
    }

    public function getTargetClassName(): string
    {
        return $this->className;
    }
}