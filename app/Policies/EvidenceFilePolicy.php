<?php

namespace App\Policies;

use App\Models\EvidenceFile;
use App\Models\Incident;
use App\Models\SafetyInspection;
use App\Models\User;

class EvidenceFilePolicy
{
    /**
     * Evidence is sensitive: internal operational roles, or the customer whose session it belongs to.
     */
    public function view(User $user, EvidenceFile $evidence): bool
    {
        if ($user->hasAnyRole(User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR)) {
            return true;
        }

        $attachable = $evidence->attachable;
        $session = match (true) {
            $attachable instanceof SafetyInspection, $attachable instanceof Incident => $attachable->session,
            default => null,
        };

        return $session !== null && $session->customer_id === $user->id;
    }
}
