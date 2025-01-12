<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Metadata\Parser;

use const JSON_THROW_ON_ERROR;
use function json_decode;
use function str_starts_with;
use PHPUnit\Framework\Attributes\After as AfterAttribute;
use PHPUnit\Framework\Attributes\AfterClass as AfterClassAttribute;
use PHPUnit\Framework\Attributes\BackupGlobals as BackupGlobalsAttribute;
use PHPUnit\Framework\Attributes\BackupStaticProperties as BackupStaticPropertiesAttribute;
use PHPUnit\Framework\Attributes\Before as BeforeAttribute;
use PHPUnit\Framework\Attributes\BeforeClass as BeforeClassAttribute;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore as CodeCoverageIgnoreAttribute;
use PHPUnit\Framework\Attributes\CoversClass as CoversClassAttribute;
use PHPUnit\Framework\Attributes\CoversFunction as CoversFunctionAttribute;
use PHPUnit\Framework\Attributes\CoversNothing as CoversNothingAttribute;
use PHPUnit\Framework\Attributes\DataProvider as DataProviderAttribute;
use PHPUnit\Framework\Attributes\DataProviderExternal as DataProviderExternalAttribute;
use PHPUnit\Framework\Attributes\Depends as DependsAttribute;
use PHPUnit\Framework\Attributes\DependsExternal as DependsExternalAttribute;
use PHPUnit\Framework\Attributes\DependsExternalUsingDeepClone as DependsExternalUsingDeepCloneAttribute;
use PHPUnit\Framework\Attributes\DependsExternalUsingShallowClone as DependsExternalUsingShallowCloneAttribute;
use PHPUnit\Framework\Attributes\DependsOnClass as DependsOnClassAttribute;
use PHPUnit\Framework\Attributes\DependsOnClassUsingDeepClone as DependsOnClassUsingDeepCloneAttribute;
use PHPUnit\Framework\Attributes\DependsOnClassUsingShallowClone as DependsOnClassUsingShallowCloneAttribute;
use PHPUnit\Framework\Attributes\DependsUsingDeepClone as DependsUsingDeepCloneAttribute;
use PHPUnit\Framework\Attributes\DependsUsingShallowClone as DependsUsingShallowCloneAttribute;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions as DoesNotPerformAssertionsAttribute;
use PHPUnit\Framework\Attributes\ExcludeGlobalVariableFromBackup as ExcludeGlobalVariableFromBackupAttribute;
use PHPUnit\Framework\Attributes\ExcludeStaticPropertyFromBackup as ExcludeStaticPropertyFromBackupAttribute;
use PHPUnit\Framework\Attributes\Group as GroupAttribute;
use PHPUnit\Framework\Attributes\Large as LargeAttribute;
use PHPUnit\Framework\Attributes\Medium as MediumAttribute;
use PHPUnit\Framework\Attributes\PostCondition as PostConditionAttribute;
use PHPUnit\Framework\Attributes\PreCondition as PreConditionAttribute;
use PHPUnit\Framework\Attributes\PreserveGlobalState as PreserveGlobalStateAttribute;
use PHPUnit\Framework\Attributes\RequiresFunction as RequiresFunctionAttribute;
use PHPUnit\Framework\Attributes\RequiresMethod as RequiresMethodAttribute;
use PHPUnit\Framework\Attributes\RequiresOperatingSystem as RequiresOperatingSystemAttribute;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily as RequiresOperatingSystemFamilyAttribute;
use PHPUnit\Framework\Attributes\RequiresPhp as RequiresPhpAttribute;
use PHPUnit\Framework\Attributes\RequiresPhpExtension as RequiresPhpExtensionAttribute;
use PHPUnit\Framework\Attributes\RequiresPhpunit as RequiresPhpunitAttribute;
use PHPUnit\Framework\Attributes\RequiresSetting as RequiresSettingAttribute;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess as RunClassInSeparateProcessAttribute;
use PHPUnit\Framework\Attributes\RunInSeparateProcess as RunInSeparateProcessAttribute;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses as RunTestsInSeparateProcessesAttribute;
use PHPUnit\Framework\Attributes\Small as SmallAttribute;
use PHPUnit\Framework\Attributes\Test as TestAttribute;
use PHPUnit\Framework\Attributes\TestDox as TestDoxAttribute;
use PHPUnit\Framework\Attributes\TestWith as TestWithAttribute;
use PHPUnit\Framework\Attributes\TestWithJson as TestWithJsonAttribute;
use PHPUnit\Framework\Attributes\Ticket as TicketAttribute;
use PHPUnit\Framework\Attributes\UsesClass as UsesClassAttribute;
use PHPUnit\Framework\Attributes\UsesFunction as UsesFunctionAttribute;
use PHPUnit\Metadata\After;
use PHPUnit\Metadata\AfterClass;
use PHPUnit\Metadata\BackupGlobals;
use PHPUnit\Metadata\BackupStaticProperties;
use PHPUnit\Metadata\Before;
use PHPUnit\Metadata\BeforeClass;
use PHPUnit\Metadata\CodeCoverageIgnore;
use PHPUnit\Metadata\CoversClass;
use PHPUnit\Metadata\CoversFunction;
use PHPUnit\Metadata\CoversNothing;
use PHPUnit\Metadata\DataProvider;
use PHPUnit\Metadata\DependsOnClass;
use PHPUnit\Metadata\DependsOnMethod;
use PHPUnit\Metadata\DoesNotPerformAssertions;
use PHPUnit\Metadata\ExcludeGlobalVariableFromBackup;
use PHPUnit\Metadata\ExcludeStaticPropertyFromBackup;
use PHPUnit\Metadata\Group;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\Metadata\PostCondition;
use PHPUnit\Metadata\PreCondition;
use PHPUnit\Metadata\PreserveGlobalState;
use PHPUnit\Metadata\RequiresFunction;
use PHPUnit\Metadata\RequiresMethod;
use PHPUnit\Metadata\RequiresOperatingSystem;
use PHPUnit\Metadata\RequiresOperatingSystemFamily;
use PHPUnit\Metadata\RequiresPhp;
use PHPUnit\Metadata\RequiresPhpExtension;
use PHPUnit\Metadata\RequiresPhpunit;
use PHPUnit\Metadata\RequiresSetting;
use PHPUnit\Metadata\RunClassInSeparateProcess;
use PHPUnit\Metadata\RunInSeparateProcess;
use PHPUnit\Metadata\RunTestsInSeparateProcesses;
use PHPUnit\Metadata\Test;
use PHPUnit\Metadata\TestDox;
use PHPUnit\Metadata\TestWith;
use PHPUnit\Metadata\UsesClass;
use PHPUnit\Metadata\UsesFunction;
use PHPUnit\Metadata\Version\ConstraintRequirement;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class AttributeParser implements Parser
{
    /**
     * @psalm-param class-string $className
     */
    public function forClass(string $className): MetadataCollection
    {
        $result = [];

        foreach ((new ReflectionClass($className))->getAttributes() as $attribute) {
            if (!str_starts_with($attribute->getName(), 'PHPUnit\\Framework\\Attributes\\')) {
                continue;
            }

            $attributeInstance = $attribute->newInstance();

            switch ($attribute->getName()) {
                case BackupGlobalsAttribute::class:
                    $result[] = new BackupGlobals($attributeInstance->enabled());

                    break;

                case BackupStaticPropertiesAttribute::class:
                    $result[] = new BackupStaticProperties($attributeInstance->enabled());

                    break;

                case CodeCoverageIgnoreAttribute::class:
                    $result[] = new CodeCoverageIgnore;

                    break;

                case CoversClassAttribute::class:
                    $result[] = new CoversClass($attributeInstance->className());

                    break;

                case CoversFunctionAttribute::class:
                    $result[] = new CoversFunction($attributeInstance->functionName());

                    break;

                case CoversNothingAttribute::class:
                    $result[] = new CoversNothing;

                    break;

                case DoesNotPerformAssertionsAttribute::class:
                    $result[] = new DoesNotPerformAssertions;

                    break;

                case ExcludeGlobalVariableFromBackupAttribute::class:
                    $result[] = new ExcludeGlobalVariableFromBackup($attributeInstance->globalVariableName());

                    break;

                case ExcludeStaticPropertyFromBackupAttribute::class:
                    $result[] = new ExcludeStaticPropertyFromBackup(
                        $attributeInstance->className(),
                        $attributeInstance->propertyName()
                    );

                    break;

                case GroupAttribute::class:
                    $result[] = new Group($attributeInstance->name());

                    break;

                case LargeAttribute::class:
                    $result[] = new Group('large');

                    break;

                case MediumAttribute::class:
                    $result[] = new Group('medium');

                    break;

                case PreserveGlobalStateAttribute::class:
                    $result[] = new PreserveGlobalState($attributeInstance->enabled());

                    break;

                case RequiresMethodAttribute::class:
                    $result[] = new RequiresMethod(
                        $attributeInstance->className(),
                        $attributeInstance->methodName()
                    );

                    break;

                case RequiresFunctionAttribute::class:
                    $result[] = new RequiresFunction($attributeInstance->functionName());

                    break;

                case RequiresOperatingSystemAttribute::class:
                    $result[] = new RequiresOperatingSystem($attributeInstance->regularExpression());

                    break;

                case RequiresOperatingSystemFamilyAttribute::class:
                    $result[] = new RequiresOperatingSystemFamily($attributeInstance->operatingSystemFamily());

                    break;

                case RequiresPhpAttribute::class:
                    $result[] = new RequiresPhp(
                        ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        )
                    );

                    break;

                case RequiresPhpExtensionAttribute::class:
                    $versionConstraint = null;

                    if ($attributeInstance->hasVersionRequirement()) {
                        $versionConstraint = ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        );
                    }

                    $result[] = new RequiresPhpExtension(
                        $attributeInstance->extension(),
                        $versionConstraint
                    );

                    break;

                case RequiresPhpunitAttribute::class:
                    $result[] = new RequiresPhpunit(
                        ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        )
                    );

                    break;

                case RequiresSettingAttribute::class:
                    $result[] = new RequiresSetting(
                        $attributeInstance->setting(),
                        $attributeInstance->value()
                    );

                    break;

                case RunClassInSeparateProcessAttribute::class:
                    $result[] = new RunClassInSeparateProcess;

                    break;

                case RunTestsInSeparateProcessesAttribute::class:
                    $result[] = new RunTestsInSeparateProcesses;

                    break;

                case SmallAttribute::class:
                    $result[] = new Group('small');

                    break;

                case TestDoxAttribute::class:
                    $result[] = new TestDox($attributeInstance->text());

                    break;

                case TicketAttribute::class:
                    $result[] = new Group($attributeInstance->text());

                    break;

                case UsesClassAttribute::class:
                    $result[] = new UsesClass($attributeInstance->className());

                    break;

                case UsesFunctionAttribute::class:
                    $result[] = new UsesFunction($attributeInstance->functionName());

                    break;
            }
        }

        return MetadataCollection::fromArray($result);
    }

    /**
     * @psalm-param class-string $className
     */
    public function forMethod(string $className, string $methodName): MetadataCollection
    {
        $result = [];

        foreach ((new ReflectionMethod($className, $methodName))->getAttributes() as $attribute) {
            if (!str_starts_with($attribute->getName(), 'PHPUnit\\Framework\\Attributes\\')) {
                continue;
            }

            $attributeInstance = $attribute->newInstance();

            switch ($attribute->getName()) {
                case AfterAttribute::class:
                    $result[] = new After;

                    break;

                case AfterClassAttribute::class:
                    $result[] = new AfterClass;

                    break;

                case BackupGlobalsAttribute::class:
                    $result[] = new BackupGlobals($attributeInstance->enabled());

                    break;

                case BackupStaticPropertiesAttribute::class:
                    $result[] = new BackupStaticProperties($attributeInstance->enabled());

                    break;

                case BeforeAttribute::class:
                    $result[] = new Before;

                    break;

                case BeforeClassAttribute::class:
                    $result[] = new BeforeClass;

                    break;

                case CodeCoverageIgnoreAttribute::class:
                    $result[] = new CodeCoverageIgnore;

                    break;

                case CoversNothingAttribute::class:
                    $result[] = new CoversNothing;

                    break;

                case DataProviderAttribute::class:
                    $result[] = new DataProvider($className, $attributeInstance->methodName());

                    break;

                case DataProviderExternalAttribute::class:
                    $result[] = new DataProvider($attributeInstance->className(), $attributeInstance->methodName());

                    break;

                case DependsAttribute::class:
                    $result[] = new DependsOnMethod($className, $attributeInstance->methodName(), false, false);

                    break;

                case DependsUsingDeepCloneAttribute::class:
                    $result[] = new DependsOnMethod($className, $attributeInstance->methodName(), true, false);

                    break;

                case DependsUsingShallowCloneAttribute::class:
                    $result[] = new DependsOnMethod($className, $attributeInstance->methodName(), false, true);

                    break;

                case DependsExternalAttribute::class:
                    $result[] = new DependsOnMethod($attributeInstance->className(), $attributeInstance->methodName(), false, false);

                    break;

                case DependsExternalUsingDeepCloneAttribute::class:
                    $result[] = new DependsOnMethod($attributeInstance->className(), $attributeInstance->methodName(), true, false);

                    break;

                case DependsExternalUsingShallowCloneAttribute::class:
                    $result[] = new DependsOnMethod($attributeInstance->className(), $attributeInstance->methodName(), false, true);

                    break;

                case DependsOnClassAttribute::class:
                    $result[] = new DependsOnClass($attributeInstance->className(), false, false);

                    break;

                case DependsOnClassUsingDeepCloneAttribute::class:
                    $result[] = new DependsOnClass($attributeInstance->className(), true, false);

                    break;

                case DependsOnClassUsingShallowCloneAttribute::class:
                    $result[] = new DependsOnClass($attributeInstance->className(), false, true);

                    break;

                case DoesNotPerformAssertionsAttribute::class:
                    $result[] = new DoesNotPerformAssertions;

                    break;

                case ExcludeGlobalVariableFromBackupAttribute::class:
                    $result[] = new ExcludeGlobalVariableFromBackup($attributeInstance->globalVariableName());

                    break;

                case ExcludeStaticPropertyFromBackupAttribute::class:
                    $result[] = new ExcludeStaticPropertyFromBackup(
                        $attributeInstance->className(),
                        $attributeInstance->propertyName()
                    );

                    break;

                case GroupAttribute::class:
                    $result[] = new Group($attributeInstance->name());

                    break;

                case PostConditionAttribute::class:
                    $result[] = new PostCondition;

                    break;

                case PreConditionAttribute::class:
                    $result[] = new PreCondition;

                    break;

                case PreserveGlobalStateAttribute::class:
                    $result[] = new PreserveGlobalState($attributeInstance->enabled());

                    break;

                case RequiresMethodAttribute::class:
                    $result[] = new RequiresMethod(
                        $attributeInstance->className(),
                        $attributeInstance->methodName()
                    );

                    break;

                case RequiresFunctionAttribute::class:
                    $result[] = new RequiresFunction($attributeInstance->functionName());

                    break;

                case RequiresOperatingSystemAttribute::class:
                    $result[] = new RequiresOperatingSystem($attributeInstance->regularExpression());

                    break;

                case RequiresOperatingSystemFamilyAttribute::class:
                    $result[] = new RequiresOperatingSystemFamily($attributeInstance->operatingSystemFamily());

                    break;

                case RequiresPhpAttribute::class:
                    $result[] = new RequiresPhp(
                        ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        )
                    );

                    break;

                case RequiresPhpExtensionAttribute::class:
                    $versionConstraint = null;

                    if ($attributeInstance->hasVersionRequirement()) {
                        $versionConstraint = ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        );
                    }

                    $result[] = new RequiresPhpExtension(
                        $attributeInstance->extension(),
                        $versionConstraint
                    );

                    break;

                case RequiresPhpunitAttribute::class:
                    $result[] = new RequiresPhpunit(
                        ConstraintRequirement::from(
                            $attributeInstance->versionRequirement()
                        )
                    );

                    break;

                case RequiresSettingAttribute::class:
                    $result[] = new RequiresSetting(
                        $attributeInstance->setting(),
                        $attributeInstance->value()
                    );

                    break;

                case RunInSeparateProcessAttribute::class:
                    $result[] = new RunInSeparateProcess;

                    break;

                case TestAttribute::class:
                    $result[] = new Test;

                    break;

                case TestDoxAttribute::class:
                    $result[] = new TestDox($attributeInstance->text());

                    break;

                case TestWithAttribute::class:
                    $result[] = new TestWith($attributeInstance->data());

                    break;

                case TestWithJsonAttribute::class:
                    $result[] = new TestWith(json_decode($attributeInstance->json(), true, 512, JSON_THROW_ON_ERROR));

                    break;

                case TicketAttribute::class:
                    $result[] = new Group($attributeInstance->text());

                    break;
            }
        }

        return MetadataCollection::fromArray($result);
    }

    /**
     * @psalm-param class-string $className
     */
    public function forClassAndMethod(string $className, string $methodName): MetadataCollection
    {
        return $this->forClass($className)->mergeWith(
            $this->forMethod($className, $methodName)
        );
    }
}
