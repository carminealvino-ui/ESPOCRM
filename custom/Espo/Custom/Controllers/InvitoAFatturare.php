<?php
namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Custom\Actions\InvitoAFatturare\GeneraDaProvvigioni;
use Espo\Core\Exceptions\BadRequest;

class InvitoAFatturare extends Record
{
    public function postActionGeneraDaProvvigioni(Request $request): object
    {
        return $this->injectableFactory->create(GeneraDaProvvigioni::class)->run($request);
    }
    public function postActionAggiungiProvvigioni(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\AggiungiProvvigioni::class)->run($request);
    }
    public function getActionProvvigioniIncluse(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\AggiungiProvvigioni::class)->included($request);
    }
    public function getActionStorniDisponibili(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\Storni::class)->available($request);
    }
    public function postActionAggiungiStorni(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\Storni::class)->run($request);
    }
    public function getActionRiepilogoVoci(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\Storni::class)->summary($request);
    }
    public function postActionRipartisciCliente(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\RipartisciCliente::class)->run($request);
    }
    public function postActionEmetti(Request $request): object
    {
        throw new BadRequest('Inserire numero e data fattura e salvare lo stato Definitivo.');
    }
    public function getActionProvvigioniStoriche(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\Historical::class)->search($request);
    }
    public function postActionAggiungiProvvigioniStoriche(Request $request): object
    {
        return $this->injectableFactory->create(\Espo\Custom\Actions\InvitoAFatturare\Historical::class)->add($request);
    }
}
