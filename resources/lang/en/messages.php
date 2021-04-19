<?php

return [
    'custom'               => [
        '201' => 'successfully created',
        '400' => 'the given data is invalid',
        '401' => 'your api key is unauthorized',
        '403' => 'forbidden',
        '404' => 'resource not found',
        '405' => 'method not allowed',
        '408' => 'request timeout',
        '409' => 'resource conflict',
        '429' => 'too many request exception, retry after :retry hours',
        '500' => 'internal server error',
        '503' => 'Database Error',
        'token' => [
            'revoke' => 'successfully revoked',
            'client_revoke_notice' => 'your old token is going to revoke in 24 hours'
        ],
        'error' => [
            'no_data' => 'your request has no content or content is not valid',
            'try_later' => 'please try later',
            'exist_plan_for_next_period' => 'you can not create new invoice, because have plan for next period!',
            'fields_not_supplied' => 'fields not supplied',
            'validation_regex' => 'validation.regex',
            'empty_result' => 'empty result',
            'not_found' => 'not found',
            'resource_not_found' => 'resource not found',
            'model_not_found' => 'requested :model not found',
            'unauthorized' => 'unauthorized',
            'query' => 'query exception'
        ],
        'success' => [
            'update' => 'updated successfully',
            'create' => 'successfully created',
            'delete' => 'successfully deleted',
            'validation_email_sent' => 'validation email sent',
            'ok_validation' => 'your account has been successfully validated',
            'forgot_password' => 'please check your email to continue!',
            'unsubscribe' => 'you have unsubscribed successfully'
        ]
    ]
];
