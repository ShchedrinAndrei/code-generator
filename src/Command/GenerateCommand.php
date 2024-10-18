<?php

declare(strict_types=1);

namespace Shchandrei\CodeGenerator\Command;

use cebe\openapi\exceptions\IOException;
use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\Reader;
use League\Flysystem\FilesystemException;
use Shchandrei\CodeGenerator\FileSystem\PhpCodeDumper;
use Shchandrei\CodeGenerator\FileSystem\PhpCodeEraser;
use Shchandrei\CodeGenerator\Generator\GeneratorInterface;
use Shchandrei\CodeGenerator\Model\ClassLikeCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[
    AsCommand(
        name: 'generate:server:controller',
        description: 'Generate server controllers, models, handlers and tests from an OpenAPI specification'
    )
]
final class GenerateCommand extends Command
{
    public const SPEC_FILE_PATH = 'spec_file_path';

    /** @param GeneratorInterface[] $generators */
    public function __construct(
        private readonly iterable $generators,
        private readonly PhpCodeEraser $codeEraser,
        private readonly PhpCodeDumper $codeDumper,
        private readonly string $specFileLocation,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Generate request and response models'
            )
            ->setDefinition([
                new InputArgument(
                    self::SPEC_FILE_PATH,
                    InputArgument::OPTIONAL,
                    'Path to the custom OpenAPI spec file'
                ),
            ]);
    }

    /**
     * @throws UnresolvableReferenceException
     * @throws IOException
     * @throws TypeErrorException
     * @throws FilesystemException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $location = $input->getArgument(self::SPEC_FILE_PATH) ?? $this->specFileLocation;
        $schema = Reader::readFromYamlFile(realpath($location));

        if (!$schema->validate()) {
            foreach ($schema->getErrors() as $error) {
                $io->error($error);
            }
            $io->error('Invalid openapi scheme');

            return self::FAILURE;
        }

        $collection = new ClassLikeCollection();
        $cleanUpNs = [];

        foreach ($this->generators as $generator) {
            foreach ($schema->paths as $url => $endpoint) {
                foreach ($endpoint->getOperations() as $method => $operation) {
                    $collection->mergeIn(
                        $generator->generate(
                            $url,
                            $method,
                            $endpoint->parameters,
                            $operation
                        )
                    );
                }
            }

            $cleanUpNs = array_merge(iterator_to_array($generator->getCleanUpNs()), $cleanUpNs);
        }

        foreach ($cleanUpNs as $nsEnd => $nsStart) {
            /** @var string $nsEnd */
            $io->writeln(
                $this->codeEraser->deleteMatchingNamespaces($nsStart, $nsEnd)
            );
        }

        foreach ($this->codeDumper->dumpClassLikes($collection) as $message) {
            $io->writeln($message);
        }

        $io->success('Generation succeeded!');

        return self::SUCCESS;
    }
}
