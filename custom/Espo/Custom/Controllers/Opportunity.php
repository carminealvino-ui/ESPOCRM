<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;
use Espo\ORM\EntityManager;

/**
 * Controller base Opportunity.
 *
 * Necessario quando scope/metadata lato server risolve il controller
 * nel namespace Custom. Le action custom restano gestite da app/actions.
 */
class Opportunity extends Record
{
    public function postActionCreateContratto(
        Request $request,
        Response $response
    ): object {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new \Exception('ID mancante');
        }

        $action = $this->resolveCreateContrattoAction();

        return $action->run($id);
    }

    private function resolveCreateContrattoAction(): \Espo\Custom\Actions\Opportunity\CreateContratto
    {
        $injectableFactory = $this->resolveInjectableFactory();

        if ($injectableFactory) {
            return $injectableFactory->create(\Espo\Custom\Actions\Opportunity\CreateContratto::class);
        }

        $entityManager = $this->resolveEntityManager();

        return new \Espo\Custom\Actions\Opportunity\CreateContratto($entityManager);
    }

    private function resolveInjectableFactory(): ?InjectableFactory
    {
        if (method_exists($this, 'getContainer')) {
            try {
                $container = $this->getContainer();

                if ($container && method_exists($container, 'get')) {
                    $value = $container->get('injectableFactory');

                    if ($value instanceof InjectableFactory) {
                        return $value;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and continue scanning.
            }
        }

        $ref = new \ReflectionObject($this);

        foreach ($ref->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof InjectableFactory) {
                return $value;
            }

            if ($value && is_object($value) && method_exists($value, 'get')) {
                try {
                    $maybe = $value->get('injectableFactory');

                    if ($maybe instanceof InjectableFactory) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }
        }

        return null;
    }

    private function resolveEntityManager(): EntityManager
    {
        if (method_exists($this, 'getContainer')) {
            try {
                $container = $this->getContainer();

                if ($container && method_exists($container, 'get')) {
                    $value = $container->get('entityManager');

                    if ($value instanceof EntityManager) {
                        return $value;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and continue scanning.
            }
        }

        if (property_exists($this, 'entityManager')) {
            $value = $this->entityManager ?? null;

            if ($value instanceof EntityManager) {
                return $value;
            }
        }

        if (method_exists($this, 'getEntityManager')) {
            $value = $this->getEntityManager();

            if ($value instanceof EntityManager) {
                return $value;
            }
        }

        if (method_exists($this, 'getRecordService')) {
            try {
                $recordService = $this->getRecordService();

                if ($recordService && method_exists($recordService, 'getEntityManager')) {
                    $value = $recordService->getEntityManager();

                    if ($value instanceof EntityManager) {
                        return $value;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and continue scanning.
            }
        }

        $ref = new \ReflectionObject($this);

        foreach ($ref->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof EntityManager) {
                return $value;
            }

            if ($value && is_object($value) && method_exists($value, 'get')) {
                try {
                    $maybe = $value->get('entityManager');

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }

            if ($value && is_object($value) && method_exists($value, 'getEntityManager')) {
                try {
                    $maybe = $value->getEntityManager();

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }

            if ($value && is_object($value) && $value instanceof \ArrayAccess) {
                try {
                    $maybe = $value['entityManager'] ?? null;

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }

            if ($value && is_object($value) && method_exists($value, 'create')) {
                try {
                    $maybe = $value->create(EntityManager::class);

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }
        }

        if (class_exists(\Espo\Core\Application::class)) {
            try {
                if (method_exists(\Espo\Core\Application::class, 'getContainer')) {
                    $container = \Espo\Core\Application::getContainer();

                    if ($container && method_exists($container, 'get')) {
                        $maybe = $container->get('entityManager');

                        if ($maybe instanceof EntityManager) {
                            return $maybe;
                        }
                    }
                }

                if (method_exists(\Espo\Core\Application::class, 'getInstance')) {
                    $app = \Espo\Core\Application::getInstance();

                    if ($app && method_exists($app, 'getContainer')) {
                        $container = $app->getContainer();

                        if ($container && method_exists($container, 'get')) {
                            $maybe = $container->get('entityManager');

                            if ($maybe instanceof EntityManager) {
                                return $maybe;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and continue scanning.
            }
        }

        foreach (['container', 'appContainer', 'slimContainer'] as $globalKey) {
            if (!isset($GLOBALS[$globalKey])) {
                continue;
            }

            $globalContainer = $GLOBALS[$globalKey];

            if ($globalContainer && is_object($globalContainer) && method_exists($globalContainer, 'get')) {
                try {
                    $maybe = $globalContainer->get('entityManager');

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue scanning.
                }
            }
        }

        throw new \RuntimeException('EntityManager non disponibile nel controller Opportunity.');
    }
}
