<?php

elgg_make_sticky_form('event_unsubscribe');

$guid = (int) get_input('guid');
$email = get_input('email');

elgg_entity_gatekeeper($guid, 'object', Event::SUBTYPE);
$entity = get_entity($guid);

if (!empty($email) && !is_email_address($email)) {
	register_error(elgg_echo('registration:notemail'));
	forward(REFERER);
}
			
// try to find a registration
$registrations = elgg_get_entities_from_metadata([
	'type' => 'object',
	'subtype' => EventRegistration::SUBTYPE,
	'owner_guid' => $entity->getGUID(),
	'limit' => 1,
	'metadata_name_value_pairs' => [
		'name' => 'email',
		'value' => $email,
		'case_sensitive' => false,
	],
]);

if (empty($registrations)) {
	register_error(elgg_echo('event_manager:action:unsubscribe:error:no_registration'));
	forward(REFERER);
}

$registration = $registrations[0];

// generate unsubscribe code
$unsubscribe_code = event_manager_create_unsubscribe_code($registration, $entity);
$unsubscribe_link = elgg_normalize_url("events/unsubscribe/confirm/{$registration->getGUID()}/" . $unsubscribe_code);

// make a message with further instructions
$subject = elgg_echo('event_manager:unsubscribe:confirm:subject', [$entity->title]);
$message = elgg_echo('event_manager:unsubscribe:confirm:message', [
	$registration->name,
	$entity->title,
	$entity->getURL(),
	$unsubscribe_link,
]);

// nice e-mail addresses
$site = elgg_get_site_entity();
if ($site->email) {
	$from = $site->name . " <{$site->email}>";
} else {
	$from = $site->name . " <noreply@{$site->getDomain()}>";
}

$to = $registration->name . " <{$registration->email}>";

if (!elgg_send_email($from, $to, $subject, $message)) {
	register_error(elgg_echo('event_manager:action:unsubscribe:error:mail'));
	forward(REFERER);
}

elgg_clear_sticky_form('event_unsubscribe');

system_message(elgg_echo('event_manager:action:unsubscribe:success'));

forward($entity->getURL());
