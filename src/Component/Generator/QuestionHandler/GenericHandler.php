<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\QuestionHandler;

use Frosh\DevelopmentHelper\Component\Generator\Definition\QuestionHelper;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class GenericHandler implements QuestionHandlerInterface
{
    /**
     * @var array
     */
    private $entityDefinitions;

    public function __construct(array $entityDefinitions)
    {
        $this->entityDefinitions = $entityDefinitions;
    }

    public function supports(string $field): bool
    {
        return true;
    }

    public function handle(Definition $definition, SymfonyStyle $io, string $name, string $type): ?Field
    {
        $type = 'Shopware\Core\Framework\DataAbstractionLayer\Field\\' . $type;

        $ref = new \ReflectionClass($type);
        $parameters = $ref->getConstructor()->getParameters();

        $args = [];
        foreach ($parameters as $parameter) {
            if ($parameter->name === 'propertyName') {
                $args[] = $name;
                continue;
            }

            if ($parameter->name === 'referenceClass') {
                $question = new Question('Reference Class');
                $question->setAutocompleterValues($this->entityDefinitions);
                $question->setValidator(function ($value) {
                    if (!in_array($value, $this->entityDefinitions, true)) {
                        throw new \InvalidArgumentException(sprintf('%s is an invalid reference class', $value));
                    }

                    return $value;
                });
                $value = $io->askQuestion($question);

                $args[] = $value . '::class';
                continue;
            }

            $default = null;
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
            }

            if ($parameter->name === 'storageName' && $default === null) {
                $default = (new CamelCaseToSnakeCaseNameConverter())->normalize($name);
            }

            if (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            }

            // array is currently not supported
            if (is_array($default)) {
                $args[] = $default;
                continue;
            }

            $question = new Question('Parameter ' . $parameter->name, $default);
            $answer = $io->askQuestion($question);
            if ($answer === 'true') {
                $answer = true;
            } else if($answer === 'false') {
                $answer = false;
            } else if(strlen((string) $answer) && $parameter->hasType()) {
                $parameterType = (string) $parameter->getType();

                if ($parameterType === 'int') {
                    $answer = (int) $answer;
                } else if($parameterType === 'float') {
                    $answer = (float) $answer;
                }
            }

            $args[] = $answer;
        }

        return new Field(
            $type,
            $args,
            QuestionHelper::handleFlags($io, $name, $type, $args),
            $definition->translation !== null && QuestionHelper::handleTranslationQuestion($io)
        );
    }
}
