<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
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

        $entityManager = $this->resolveEntityManager();
        $opportunity = $entityManager->getEntityById('Opportunity', $id);

        if (!$opportunity) {
            throw new \Exception('Opportunità non trovata');
        }

        $action = new \Espo\Custom\Actions\Opportunity\CreateContratto($entityManager);

        return $action->run($opportunity);
    }

    private function resolveEntityManager(): EntityManager
    {
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
        }

        if (class_exists(\Espo\Core\Application::class) && method_exists(\Espo\Core\Application::class, 'getContainer')) {
            try {
                $container = \Espo\Core\Application::getContainer();

                if ($container && method_exists($container, 'get')) {
                    $maybe = $container->get('entityManager');

                    if ($maybe instanceof EntityManager) {
                        return $maybe;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and throw generic error below.
            }
        }

        throw new \RuntimeException('EntityManager non disponibile nel controller Opportunity.');
    }
}
