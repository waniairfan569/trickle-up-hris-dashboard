<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return (int)$user->company_id === (int)$companyId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});

// Private notification channel — each user's own notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int)$user->id === (int)$userId;
});
