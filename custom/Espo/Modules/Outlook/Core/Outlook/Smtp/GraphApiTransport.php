<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Core\Outlook\Smtp;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Outlook\Core\Outlook\Clients\Outlook;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

use LogicException;

class GraphApiTransport extends AbstractTransport
{
    public function __construct(
        private Outlook $outlookClient,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return "microsoft-graph-api";
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (!$original instanceof Message) {
            throw new LogicException();
        }

        $email = MessageConverter::toEmail($original);

        $preparedEmail = $this->prepareEmail($email);

        $this->outlookClient->sendEmail($preparedEmail);
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareEmail(Email $email): array
    {
        return [
            'message' => [
                'subject' => $email->getSubject(),
                'toRecipients' => $this->prepareAddresses($email->getTo()),
                'ccRecipients' => $this->prepareAddresses($email->getCc()),
                'bccRecipients' => $this->prepareAddresses($email->getBcc()),
                'body' => $this->prepareBody($email),
                'attachments' => $this->prepareAttachments($email),
            ],
            'saveToSentItems' => 'true',
        ];
    }

    /**
     * @param Address[] $addresses
     */
    private function prepareAddresses(array $addresses): array
    {
        $output = [];

        foreach ($addresses as $address) {
            $output[] = $this->prepareAddress($address);
        }

        return $output;
    }

    private function prepareAddress(Address $address): array
    {
        $output = [
            'emailAddress' => [
                'address' => $address->getAddress(),
            ],
        ];

        if ($address->getName()) {
            $output['emailAddress']['name'] = $address->getName();
        }

        return $output;
    }

    private function prepareBody(Email $email): array
    {
        if (null !== $htmlContent = $email->getHtmlBody()) {
            return [
                'contentType' => 'html',
                'content' => $htmlContent,
            ];
        }

        if (null !== $textContent = $email->getTextBody()) {
            return [
                'contentType' => 'text',
                'content' => $textContent,
            ];
        }

        return [];
    }

    private function prepareAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $i => $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $generatedName = "inline-$i.bin";

            $item = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename') ?: $generatedName,
                'contentType' => $headers->get('Content-Type')->getBody(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];

            if ($attachment->getDisposition() === 'inline') {
                $item['isInline'] = true;
                $item['contentId'] = $attachment->getContentId();
            }

            $attachments[] = $item;
        }

        return $attachments;
    }
}
