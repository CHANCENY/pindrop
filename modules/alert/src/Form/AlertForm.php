<?php

declare(strict_types=1);

namespace Simp\Pindrop\Modules\alert\src\Form;

use Simp\Pindrop\Form\FormBase;
use Simp\Pindrop\Form\FormStateInterface;

/**
 * Alert Form
 * 
 * Handles alert settings form submission.
 */
class AlertForm extends FormBase
{
    public function getFormId(): string
    {
       return "alert_settings_form";
    }
    
    public function buildForm(array $form, FormStateInterface $formState): array
    {
       $form['basic_settings'] = [
           '#fieldset' => [
               'legend' => 'Basic Settings'
           ],
           'fields' => [
               'setting_enable' => [
                   'type' => 'checkbox',
                   'title' => 'Enable alert notifications',
                   'value' => 1
               ],
               'log_level' => [
                   'type' => 'select',
                   'title' => 'Log Level',
                   'value' => 'error',
                   'options' => [
                       ['value' => 'emergency', 'label' => 'Emergency'],
                       ['value' => 'alert', 'label' => 'Alert'],
                       ['value' => 'critical', 'label' => 'Critical'],
                       ['value' => 'error', 'label' => 'Error'],
                       ['value' => 'warning', 'label' => 'Warning'],
                       ['value' => 'notice', 'label' => 'Notice'],
                       ['value' => 'info', 'label' => 'Info'],
                       ['value' => 'debug', 'label' => 'Debug']
                   ]
               ]
           ]
       ];
       
       $form['notification_settings'] = [
           '#fieldset' => [
               'legend' => 'Notification Settings'
           ],
           'fields' => [
               'email_notifications' => [
                   'type' => 'checkbox',
                   'title' => 'Enable email notifications',
                   'value' => 0
               ],
               'email_configuration' => [
                   '#fieldset' => [
                       'legend' => 'Email Configuration'
                   ],
                   'fields' => [
                       'notification_email' => [
                           'type' => 'email',
                           'title' => 'Notification Email',
                           'value' => '',
                           'placeholder' => 'admin@example.com',
                           'required' => true
                       ],
                       'smtp_server' => [
                           'type' => 'text',
                           'title' => 'SMTP Server',
                           'value' => '',
                           'placeholder' => 'smtp.example.com'
                       ],
                       'smtp_port' => [
                           'type' => 'number',
                           'title' => 'SMTP Port',
                           'value' => 587,
                           'min' => 1,
                           'max' => 65535
                       ],
                       'smtp_username' => [
                           'type' => 'text',
                           'title' => 'SMTP Username',
                           'value' => '',
                           'placeholder' => 'username@example.com'
                       ],
                       'smtp_password' => [
                           'type' => 'password',
                           'title' => 'SMTP Password',
                           'value' => '',
                           'placeholder' => 'Enter SMTP password...'
                       ]
                   ]
               ],
               'webhook_configuration' => [
                   '#fieldset' => [
                       'legend' => 'Webhook Configuration'
                   ],
                   'fields' => [
                       'webhook_url' => [
                           'type' => 'url',
                           'title' => 'Webhook URL',
                           'value' => '',
                           'placeholder' => 'https://example.com/webhook'
                       ],
                       'webhook_method' => [
                           'type' => 'select',
                           'title' => 'HTTP Method',
                           'value' => 'POST',
                           'options' => [
                               ['value' => 'GET', 'label' => 'GET'],
                               ['value' => 'POST', 'label' => 'POST'],
                               ['value' => 'PUT', 'label' => 'PUT'],
                               ['value' => 'PATCH', 'label' => 'PATCH']
                           ]
                       ],
                       'webhook_headers' => [
                           'type' => 'textarea',
                           'title' => 'Custom Headers',
                           'value' => '',
                           'placeholder' => 'Enter custom headers in JSON format...',
                           'rows' => 4
                       ]
                   ]
               ],
               'sms_notifications' => [
                   'type' => 'checkbox',
                   'title' => 'Enable SMS notifications',
                   'value' => 0
               ],
               'sms_configuration' => [
                   '#fieldset' => [
                       'legend' => 'SMS Configuration'
                   ],
                   'fields' => [
                       'phone_number' => [
                           'type' => 'tel',
                           'title' => 'Phone Number',
                           'value' => '',
                           'placeholder' => '+1234567890',
                           'pattern' => '[+]?[0-9]{10,15}'
                       ],
                       'sms_provider' => [
                           'type' => 'select',
                           'title' => 'SMS Provider',
                           'value' => 'twilio',
                           'options' => [
                               ['value' => 'twilio', 'label' => 'Twilio'],
                               ['value' => 'nexmo', 'label' => 'Nexmo'],
                               ['value' => 'aws_sns', 'label' => 'AWS SNS']
                           ]
                       ],
                       'api_key_sms' => [
                           'type' => 'password',
                           'title' => 'SMS API Key',
                           'value' => '',
                           'placeholder' => 'Enter SMS API key...'
                       ]
                   ]
               ]
           ]
       ];
       
       $form['file_settings'] = [
           '#fieldset' => [
               'legend' => 'File Settings'
           ],
           'fields' => [
               'max_file_size' => [
                   'type' => 'number',
                   'title' => 'Maximum File Size (MB)',
                   'value' => 10,
                   'min' => 1,
                   'max' => 100
               ],
               'allowed_extensions' => [
                   'type' => 'text',
                   'title' => 'Allowed File Extensions',
                   'value' => 'jpg,png,pdf,doc,docx',
                   'placeholder' => 'jpg,png,pdf,doc,docx'
               ],
               'upload_file' => [
                   'type' => 'file',
                   'title' => 'Upload File',
                   'accept' => '.jpg,.jpeg,.png,.pdf,.doc,.docx'
               ]
           ]
       ];
       
       $form['advanced_settings'] = [
           '#fieldset' => [
               'legend' => 'Advanced Settings'
           ],
           'fields' => [
               'enable_debug' => [
                   'type' => 'checkbox',
                   'title' => 'Enable Debug Mode',
                   'value' => 0
               ],
               'timeout_seconds' => [
                   'type' => 'number',
                   'title' => 'Request Timeout (seconds)',
                   'value' => 30,
                   'min' => 5,
                   'max' => 300
               ],
               'retry_attempts' => [
                   'type' => 'range',
                   'title' => 'Retry Attempts',
                   'value' => 3,
                   'min' => 1,
                   'max' => 10
               ],
               'custom_headers' => [
                   'type' => 'textarea',
                   'title' => 'Custom Headers',
                   'value' => '',
                   'placeholder' => 'Enter custom headers in JSON format...',
                   'rows' => 6
               ]
           ]
       ];
       
       $form['schedule_settings'] = [
           '#fieldset' => [
               'legend' => 'Schedule Settings'
           ],
           'fields' => [
               'start_date' => [
                   'type' => 'date',
                   'title' => 'Start Date',
                   'value' => ''
               ],
               'end_date' => [
                   'type' => 'date',
                   'title' => 'End Date',
                   'value' => ''
               ],
               'timezone' => [
                   'type' => 'select',
                   'title' => 'Timezone',
                   'value' => 'UTC',
                   'options' => [
                       ['value' => 'UTC', 'label' => 'UTC'],
                       ['value' => 'America/New_York', 'label' => 'Eastern Time'],
                       ['value' => 'Europe/London', 'label' => 'London Time'],
                       ['value' => 'Asia/Tokyo', 'label' => 'Japan Time']
                   ]
               ],
               'frequency' => [
                   'type' => 'select',
                   'title' => 'Alert Frequency',
                   'value' => 'daily',
                   'options' => [
                       ['value' => 'immediate', 'label' => 'Immediate'],
                       ['value' => 'hourly', 'label' => 'Hourly'],
                       ['value' => 'daily', 'label' => 'Daily'],
                       ['value' => 'weekly', 'label' => 'Weekly'],
                       ['value' => 'monthly', 'label' => 'Monthly']
                   ]
               ]
           ]
       ];
       
       $form['security_settings'] = [
           '#fieldset' => [
               'legend' => 'Security Settings'
           ],
           'fields' => [
               'api_key' => [
                   'type' => 'text',
                   'title' => 'API Key',
                   'value' => '',
                   'placeholder' => 'Enter your API key...',
                   'maxlength' => 64,
                   'required' => true
               ],
               'webhook_secret' => [
                   'type' => 'password',
                   'title' => 'Webhook Secret',
                   'value' => '',
                   'placeholder' => 'Enter webhook secret...',
                   'maxlength' => 128
               ],
               'allowed_ips' => [
                   'type' => 'textarea',
                   'title' => 'Allowed IP Addresses',
                   'value' => '',
                   'placeholder' => 'Enter IP addresses (one per line)...',
                   'rows' => 4
               ]
           ]
       ];
       
       $form['advanced_options'] = [
           '#details' => [
               'summary' => 'Advanced Configuration Options',
               'open' => false
           ],
           'fields' => [
               'debug_mode' => [
                   'type' => 'checkbox',
                   'title' => 'Enable Debug Mode',
                   'value' => 0
               ],
               'log_format' => [
                   'type' => 'select',
                   'title' => 'Log Format',
                   'value' => 'json',
                   'options' => [
                       ['value' => 'json', 'label' => 'JSON'],
                       ['value' => 'xml', 'label' => 'XML'],
                       ['value' => 'text', 'label' => 'Plain Text']
                   ]
               ],
               'custom_script' => [
                   'type' => 'textarea',
                   'title' => 'Custom Script',
                   'value' => '',
                   'placeholder' => 'Enter custom JavaScript or shell script...',
                   'rows' => 8
               ],
               'experimental_features' => [
                   'type' => 'checkbox',
                   'title' => 'Enable Experimental Features',
                   'value' => 0
               ],
               'beta_access' => [
                   'type' => 'checkbox',
                   'title' => 'Enable Beta Access',
                   'value' => 0
               ]
           ]
       ];
       
       return $form;
    }
    
    public function validateForm(array $form, FormStateInterface $formState)
    {
        // Get the submitted values
        $enableAlerts = $formState->getValue('setting_enable');
        
        // Validate if needed
        if ($enableAlerts && $enableAlerts !== '1') {
            $formState->setErrorByName('setting_enable', 'Invalid value for alert setting');
        }
    }
    
    public function submitForm(array $form, FormStateInterface $formState)
    {
        // Get the validated values
        $enableAlerts = $formState->getValue('setting_enable');
        
        // Save the settings (in real app, would save to database)
        error_log('Alert settings saved: enable=' . ($enableAlerts ? 'true' : 'false'));
        
        // Set success message
        $formState->setMessage('Settings saved successfully!');
        dump($formState);
    }
}