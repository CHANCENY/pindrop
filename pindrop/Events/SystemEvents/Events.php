<?php

namespace Simp\Pindrop\Events\SystemEvents;

class Events
{
    public const string ENTITY_CREATING = 'entity.creating';
    public const string ENTITY_CREATED  = 'entity.created';

    public const string ENTITY_CREATING_DYN_FIELDS = 'entity.dyn.creating';
    public const string ENTITY_CREATED_DYN_FIELDS  = 'entity.dyn.created';

    public const string ENTITY_UPDATING = 'entity.updating';
    public const string ENTITY_UPDATED  = 'entity.updated';

    public const string ENTITY_UPDATING_DYN_FIELDS = 'entity.dyn.updating';
    public const string ENTITY_UPDATED_DYN_FIELDS  = 'entity.dyn.updated';

    public const string ENTITY_DELETING = 'entity.deleting';
    public const string ENTITY_DELETED  = 'entity.deleted';

    public const string USER_CREATING = 'user.creating';
    public const string USER_CREATED  = 'user.created';

    public const string USER_UPDATING = 'user.updating';
    public const string USER_UPDATED  = 'user.updated';

    public const string USER_DELETING = 'user.deleting';
    public const string USER_DELETED  = 'user.deleted';

    public const string AUTH_LOGIN        = 'auth.login';
    public const string AUTH_LOGOUT       = 'auth.logout';
    public const string AUTH_LOGIN_FAILED = 'auth.login_failed';

    public const string AUTH_PASSWORD_RESET = 'auth.password_reset';

    public const string ENTITY_SAVED   = 'entity.saved';
    public const string ENTITY_REMOVED = 'entity.removed';

    public const string REQUEST_RECEIVED = 'request.received';
    public const string REQUEST_HANDLED  = 'request.handled';

    public const string RESPONSE_BEFORE_SEND = 'response.before_send';
    public const string RESPONSE_SENT        = 'response.sent';
}