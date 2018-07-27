<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Services;

use \Espo\ORM\Entity;

use Espo\Core\Utils\Util;

use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\BadRequest;

class LeadCapture extends Record
{
    protected $readOnlyAttributeList = ['apiKey'];

    protected function init()
    {
        $this->addDependency('fieldManagerUtil');
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $apiKey = $this->generateApiKey();
        $entity->set('apiKey', $apiKey);
    }

    public function generateNewApiKeyForEntity($id)
    {
        $entity = $this->getEntity($id);
        if (!$entity) throw new NotFound();

        $apiKey = $this->generateApiKey();
        $entity->set('apiKey', $apiKey);

        $this->getEntityManager()->saveEntity($entity);

        return (object) [
            'apiKey' => $apiKey
        ];
    }

    public function generateApiKey()
    {
        return bin2hex(random_bytes(16));
    }

    public function leadCapture($apiKey, $data)
    {
        $leadCapture = $this->getEntityManager()->getRepository('LeadCapture')->where([
            'apiKey' => $apiKey
        ])->findOne();

        if (!$leadCapture) throw new NotFound('Api key is not valid.');

        if ($leadCapture->get('optInConfirmation') && !empty($data->emailAddress)) {
            if (!$leadCapture->get('optInConfirmationEmailTemplateId')) {
                throw new Error('No optInConfirmationEmailTemplate specified.');
            }
            $lead = $this->getLeadWithPopulatedData($leadCapture, $data);

            $job = $this->getEntityManager()->getEntity('Job');
            $job->set([
                'serviceName' => 'LeadCapture',
                'methodName' => 'optInConfirmationJob',
                'data' => (object) [
                    'leadCaptureId' => $leadCapture->id,
                    'data' => $data
                ]
            ]);
            $this->getEntityManager()->saveEntity($job);
            return true;
        }

        $this->leadCaptureProceed($leadCapture, $data);
    }

    protected function getLeadWithPopulatedData(Entity $leadCapture, $data)
    {
        $lead = $this->getEntityManager()->getEntity('Lead');

        $fieldList = $leadCapture->get('fieldList');
        if (empty($fieldList)) throw new Error('No field list specified.');

        $isEmpty = true;
        foreach ($fieldList as $field) {
            $attributeList = $this->getInjection('fieldManagerUtil')->getActualAttributeList('Lead', $field);
            if (empty($attributeList)) continue;
            foreach ($attributeList as $attribute) {
                if (property_exists($data, $attribute)) {
                    $lead->set($attribute, $data->$attribute);
                    if (!empty($data->$attribute)) {
                        $isEmpty = false;
                    }
                }
            }
        }

        if ($isEmpty) throw new BadRequest('No appropriate data in payload.');

        if ($leadCapture->get('leadSource')) {
            $lead->set('source', $leadCapture->get('leadSource'));
        }

        if ($leadCapture->get('campaignId')) {
            $lead->set('campaignId', $leadCapture->get('campaignId'));
        }

        return $lead;
    }

    public function leadCaptureProceed(Entity $leadCapture, $data)
    {
        $lead = $this->getLeadWithPopulatedData($leadCapture, $data);

        $campaingService = $this->getServiceFactory()->create('Campaign');

        if ($leadCapture->get('campaignId')) {
            $campaign = $this->getEntityManager()->getEntity('Campaign', $leadCapture->get('campaignId'));
        }

        $duplicate = null;
        $contact = null;

        if ($lead->get('emailAddress') || $lead->get('phoneNumber')) {
            $groupOr = [];
            if ($lead->get('emailAddress')) {
                $groupOr['emailAddress'] = $lead->get('emailAddress');
            }
            if ($lead->get('phoneNumber')) {
                $groupOr['phoneNumber'] = $lead->get('phoneNumber');
            }

            $duplicate = $this->getEntityManager()->getRepository('Lead')->where(['OR' => $groupOr])->findOne();
            if ($duplicate) {
                echo $duplicate->id;
            }
        }

        if ($leadCapture->get('subscribeToTargetList') && $leadCapture->get('targetListId')) {

            if ($contact) {
                if ($leadCapture->get('subscribeContactToTargetList')) {
                    $contact->addLinkMultipleId('targetLists', $leadCapture->get('targetListId'));
                    $this->getEntityManager()->saveEntity($contact);
                    // TODO Log Opt-in
                }
            } else {
                $lead->addLinkMultipleId('targetLists', $leadCapture->get('targetListId'));
                // TODO Log Opt-in
            }
        }

        if ($duplicate) {
            $lead = $duplicate;
        }

        if (!$contact) {
            if ($leadCapture->get('targetTeamId')) {
                $lead->addLinkMultipleId('teams', $leadCapture->get('targetTeamId'));
            }

            $this->getEntityManager()->saveEntity($lead);

            if (!$duplicate) {
                if ($campaign) {
                    $campaingService->logLeadCreated($campaign->id, $lead);
                }
            }
        }

        print_r($data);
        die;
    }
}
