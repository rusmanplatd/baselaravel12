--
-- PostgreSQL database dump
--

\restrict c1cpUBInhxN6AeLA5FEzolgT5n94dNgHfX9VRboUGCFRwiRum5qBM5tJahQQg0T

-- Dumped from database version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)
-- Dumped by pg_dump version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: abuse_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.abuse_reports (
    id character(26) NOT NULL,
    reported_user_id character(26),
    reporter_user_id character(26),
    conversation_id character(26),
    message_id character(26),
    abuse_type character varying(255) NOT NULL,
    description text,
    evidence json,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    resolution_notes text,
    reviewed_by character(26),
    reviewed_at timestamp(0) without time zone,
    client_ip character varying(255),
    user_agent character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id character(26) NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id character(26),
    causer_type character varying(255),
    causer_id character(26),
    properties json,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    event character varying(255),
    batch_uuid uuid,
    organization_id character(26),
    tenant_id character varying(255)
);


--
-- Name: backup_restorations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.backup_restorations (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    backup_id character(26),
    restoration_type character varying(255) NOT NULL,
    source_file_path character varying(255) NOT NULL,
    source_file_hash character varying(255) NOT NULL,
    restoration_scope json NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    status_message text,
    progress_percentage integer DEFAULT 0 NOT NULL,
    total_items bigint,
    restored_items bigint DEFAULT '0'::bigint NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    restoration_log json,
    error_log json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: backup_verification; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.backup_verification (
    id character(26) NOT NULL,
    backup_id character(26) NOT NULL,
    verification_method character varying(255) NOT NULL,
    verification_data text NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    verification_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bot_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bot_conversations (
    id character(26) NOT NULL,
    bot_id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    permissions json,
    context json,
    last_message_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bot_encryption_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bot_encryption_keys (
    id character(26) NOT NULL,
    bot_id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    key_type character varying(255) NOT NULL,
    algorithm character varying(255) NOT NULL,
    public_key text NOT NULL,
    encrypted_private_key text NOT NULL,
    key_pair_id character varying(255) NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bot_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bot_messages (
    id character(26) NOT NULL,
    bot_id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    bot_conversation_id character(26) NOT NULL,
    message_id character(26),
    direction character varying(255) NOT NULL,
    content text,
    encrypted_content text,
    encryption_version integer DEFAULT 1 NOT NULL,
    content_type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    metadata json,
    processed_at timestamp(0) without time zone,
    response_sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bots (
    id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    avatar character varying(255),
    api_token character varying(100) NOT NULL,
    webhook_url character varying(255),
    webhook_secret character varying(50),
    is_active boolean DEFAULT true NOT NULL,
    capabilities json,
    configuration json,
    rate_limit_per_minute integer DEFAULT 60 NOT NULL,
    organization_id character(26) NOT NULL,
    created_by character(26) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: chat_backups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_backups (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    conversation_id character(26),
    backup_type character varying(255) NOT NULL,
    export_format character varying(255) NOT NULL,
    backup_scope json NOT NULL,
    date_range json,
    encryption_settings json NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    status_message text,
    progress_percentage integer DEFAULT 0 NOT NULL,
    total_items bigint,
    processed_items bigint DEFAULT '0'::bigint NOT NULL,
    backup_file_path character varying(255),
    backup_file_hash character varying(255),
    backup_file_size bigint,
    include_attachments boolean DEFAULT true NOT NULL,
    include_metadata boolean DEFAULT true NOT NULL,
    preserve_encryption boolean DEFAULT true NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    error_log json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: chat_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_conversations (
    id character(26) NOT NULL,
    type character varying(255) DEFAULT 'direct'::character varying NOT NULL,
    name character varying(255),
    description text,
    avatar_url text,
    settings json,
    created_by_user_id character(26) NOT NULL,
    created_by_device_id character(26),
    organization_id character(26),
    is_active boolean DEFAULT true NOT NULL,
    last_activity_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT chat_conversations_type_check CHECK (((type)::text = ANY ((ARRAY['direct'::character varying, 'group'::character varying, 'channel'::character varying])::text[])))
);


--
-- Name: chat_encryption_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_encryption_keys (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26),
    encrypted_key text NOT NULL,
    public_key text,
    device_fingerprint character varying(255),
    key_version integer DEFAULT 1 NOT NULL,
    created_by_device_id character(26),
    algorithm character varying(255) DEFAULT 'RSA-4096-OAEP'::character varying NOT NULL,
    key_strength integer DEFAULT 4096 NOT NULL,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    last_used_at timestamp(0) without time zone,
    device_metadata json,
    revoked_at timestamp(0) without time zone,
    revocation_reason character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: chat_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_messages (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    sender_id character(26) NOT NULL,
    sender_device_id character(26) NOT NULL,
    message_type character varying(255) NOT NULL,
    encrypted_content text NOT NULL,
    encrypted_metadata json,
    content_hash text NOT NULL,
    encryption_algorithm character varying(255) DEFAULT 'signal'::character varying NOT NULL,
    encryption_version integer DEFAULT 1 NOT NULL,
    thread_id character(26),
    reply_to_id character(26),
    delivery_status json,
    is_edited boolean DEFAULT false NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    edited_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT chat_messages_message_type_check CHECK (((message_type)::text = ANY ((ARRAY['text'::character varying, 'image'::character varying, 'video'::character varying, 'audio'::character varying, 'file'::character varying, 'voice'::character varying, 'poll'::character varying, 'system'::character varying, 'call'::character varying])::text[])))
);


--
-- Name: chat_thread_participants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_thread_participants (
    id character(26) NOT NULL,
    thread_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    joined_at timestamp(0) without time zone NOT NULL,
    left_at timestamp(0) without time zone,
    last_read_message_id character(26),
    notification_settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: chat_threads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_threads (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    parent_message_id character(26) NOT NULL,
    creator_id character(26) NOT NULL,
    title character varying(255),
    encrypted_title text,
    title_hash character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    participant_count integer DEFAULT 0 NOT NULL,
    message_count integer DEFAULT 0 NOT NULL,
    last_message_at timestamp(0) without time zone,
    last_message_id character(26),
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: conversation_key_bundles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conversation_key_bundles (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    participant_id character(26) NOT NULL,
    encrypted_group_key text NOT NULL,
    encryption_algorithm character varying(255) DEFAULT 'signal'::character varying NOT NULL,
    key_version integer DEFAULT 1 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    distributed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: conversation_participants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conversation_participants (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26),
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    permissions json,
    is_muted boolean DEFAULT false NOT NULL,
    has_notifications boolean DEFAULT true NOT NULL,
    joined_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    left_at timestamp(0) without time zone,
    last_read_at timestamp(0) without time zone,
    last_read_message_id character(26),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT conversation_participants_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'moderator'::character varying, 'member'::character varying])::text[])))
);


--
-- Name: export_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.export_templates (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    template_name character varying(255) NOT NULL,
    template_type character varying(255) NOT NULL,
    template_content text NOT NULL,
    template_settings json NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_shared boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id character(26) NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: ip_restrictions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ip_restrictions (
    id character(26) NOT NULL,
    ip_address character varying(255) NOT NULL,
    restriction_type character varying(255) NOT NULL,
    reason character varying(255) NOT NULL,
    description text,
    violation_count integer DEFAULT 1 NOT NULL,
    restriction_settings json NOT NULL,
    first_violation_at timestamp(0) without time zone NOT NULL,
    last_violation_at timestamp(0) without time zone NOT NULL,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    applied_by character(26),
    admin_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: message_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_attachments (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    filename character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    file_size bigint NOT NULL,
    encrypted_file_key text NOT NULL,
    encrypted_storage_path text NOT NULL,
    file_hash text NOT NULL,
    thumbnail_encrypted text,
    metadata json,
    encryption_algorithm character varying(255) DEFAULT 'aes-256-gcm'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_delivery_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_delivery_receipts (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    recipient_user_id character(26) NOT NULL,
    recipient_device_id character(26) NOT NULL,
    status character varying(255) DEFAULT 'sent'::character varying NOT NULL,
    delivered_at timestamp(0) without time zone,
    read_at timestamp(0) without time zone,
    failure_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT message_delivery_receipts_status_check CHECK (((status)::text = ANY ((ARRAY['sent'::character varying, 'delivered'::character varying, 'read'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: message_files; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_files (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    original_filename character varying(255) NOT NULL,
    encrypted_filename character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    file_size bigint NOT NULL,
    encrypted_size bigint NOT NULL,
    file_hash character varying(255) NOT NULL,
    encryption_key_encrypted json NOT NULL,
    thumbnail_path character varying(255),
    thumbnail_encrypted boolean DEFAULT false NOT NULL,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_mentions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_mentions (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    mentioned_user_id character(26) NOT NULL,
    mention_type character varying(255) DEFAULT 'user'::character varying NOT NULL,
    start_position integer NOT NULL,
    length integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT message_mentions_mention_type_check CHECK (((mention_type)::text = ANY ((ARRAY['user'::character varying, 'all'::character varying, 'here'::character varying])::text[])))
);


--
-- Name: message_poll_votes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_poll_votes (
    id character(26) NOT NULL,
    poll_id character(26) NOT NULL,
    voter_user_id character(26) NOT NULL,
    voter_device_id character(26) NOT NULL,
    encrypted_choices text NOT NULL,
    vote_hash text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_polls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_polls (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    encrypted_question text NOT NULL,
    encrypted_options json NOT NULL,
    allows_multiple_choices boolean DEFAULT false NOT NULL,
    is_anonymous boolean DEFAULT false NOT NULL,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_reactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_reactions (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    reaction_type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: mobile_pass_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mobile_pass_devices (
    id character varying(255) NOT NULL,
    push_token character varying(255) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: mobile_pass_registrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mobile_pass_registrations (
    id character(26) NOT NULL,
    device_id character varying(255) NOT NULL,
    pass_type_id character varying(255) NOT NULL,
    pass_serial character(26) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: mobile_passes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mobile_passes (
    id character(26) NOT NULL,
    type character varying(255) NOT NULL,
    builder_name character varying(255) NOT NULL,
    content json NOT NULL,
    images json NOT NULL,
    model_type character varying(255),
    model_id bigint,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: notification_log_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_log_items (
    id character(26) NOT NULL,
    notification_type character varying(255) NOT NULL,
    notifiable_id bigint,
    notifiable_type character varying(255),
    channel character varying(255) NOT NULL,
    fingerprint character varying(255),
    extra json,
    anonymous_notifiable_properties json,
    confirmed_at timestamp(0) without time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: oauth_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_access_tokens (
    id character(80) NOT NULL,
    user_id character(26),
    client_id character(26) NOT NULL,
    name character varying(255),
    scopes text,
    revoked boolean NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    expires_at timestamp(0) without time zone
);


--
-- Name: oauth_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_audit_logs (
    id bigint NOT NULL,
    event_type character varying(255) NOT NULL,
    client_id character(26),
    user_id character(26),
    ip_address inet NOT NULL,
    user_agent character varying(255),
    scopes json,
    grant_type character varying(255),
    success boolean DEFAULT true NOT NULL,
    error_code character varying(255),
    error_description text,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    organization_id character(26),
    tenant_id character(26),
    tenant_domain character varying(255),
    organization_context json
);


--
-- Name: oauth_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.oauth_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: oauth_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.oauth_audit_logs_id_seq OWNED BY public.oauth_audit_logs.id;


--
-- Name: oauth_auth_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_auth_codes (
    id character(80) NOT NULL,
    user_id character(26) NOT NULL,
    client_id character(26) NOT NULL,
    scopes text,
    revoked boolean NOT NULL,
    expires_at timestamp(0) without time zone,
    code_challenge character varying(128),
    code_challenge_method character varying(10)
);


--
-- Name: oauth_clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_clients (
    id character(26) NOT NULL,
    owner_type character varying(255),
    owner_id character(26),
    name character varying(255) NOT NULL,
    secret character varying(255),
    provider character varying(255),
    redirect_uris text NOT NULL,
    grant_types text NOT NULL,
    revoked boolean NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    description text,
    website character varying(255),
    logo_url character varying(255),
    contacts json,
    tos_uri character varying(255),
    policy_uri character varying(255),
    organization_id character(26),
    allowed_scopes json,
    client_type character varying(255) DEFAULT 'confidential'::character varying NOT NULL,
    last_used_at timestamp(0) without time zone,
    user_access_scope character varying(255) NOT NULL,
    user_access_rules json,
    CONSTRAINT oauth_clients_user_access_scope_check CHECK (((user_access_scope)::text = ANY ((ARRAY['all_users'::character varying, 'organization_members'::character varying, 'custom'::character varying])::text[])))
);


--
-- Name: COLUMN oauth_clients.user_access_scope; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.oauth_clients.user_access_scope IS 'Controls which users can access this OAuth client';


--
-- Name: COLUMN oauth_clients.user_access_rules; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.oauth_clients.user_access_rules IS 'Custom rules for user access when user_access_scope is custom';


--
-- Name: oauth_refresh_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_refresh_tokens (
    id character(80) NOT NULL,
    access_token_id character(80) NOT NULL,
    revoked boolean NOT NULL,
    expires_at timestamp(0) without time zone
);


--
-- Name: oauth_scopes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.oauth_scopes (
    id bigint NOT NULL,
    identifier character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: oauth_scopes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.oauth_scopes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: oauth_scopes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.oauth_scopes_id_seq OWNED BY public.oauth_scopes.id;


--
-- Name: one_time_passwords; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.one_time_passwords (
    id character(26) NOT NULL,
    password character varying(255) NOT NULL,
    origin_properties json,
    expires_at timestamp(0) without time zone NOT NULL,
    authenticatable_type character varying(255) NOT NULL,
    authenticatable_id bigint NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: organization_memberships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organization_memberships (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    organization_id character(26) NOT NULL,
    organization_unit_id character(26),
    organization_position_id character(26),
    membership_type character varying(255) DEFAULT 'employee'::character varying NOT NULL,
    start_date date NOT NULL,
    end_date date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    additional_roles json,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL,
    CONSTRAINT organization_memberships_membership_type_check CHECK (((membership_type)::text = ANY (ARRAY[('employee'::character varying)::text, ('board_member'::character varying)::text, ('consultant'::character varying)::text, ('contractor'::character varying)::text, ('intern'::character varying)::text, ('manager'::character varying)::text]))),
    CONSTRAINT organization_memberships_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'terminated'::character varying])::text[])))
);


--
-- Name: organization_position_levels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organization_position_levels (
    id character(26) NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    hierarchy_level integer NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: COLUMN organization_position_levels.hierarchy_level; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.organization_position_levels.hierarchy_level IS 'Lower numbers = higher hierarchy';


--
-- Name: organization_positions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organization_positions (
    id character(26) NOT NULL,
    organization_id character(26) NOT NULL,
    organization_unit_id character(26) NOT NULL,
    position_code character varying(255) NOT NULL,
    organization_position_level_id character(26) NOT NULL,
    title character varying(255) NOT NULL,
    job_description text,
    qualifications json,
    responsibilities json,
    min_salary numeric(12,2),
    max_salary numeric(12,2),
    is_active boolean DEFAULT true NOT NULL,
    max_incumbents integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: organization_units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organization_units (
    id character(26) NOT NULL,
    organization_id character(26) NOT NULL,
    unit_code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    unit_type character varying(255) NOT NULL,
    description text,
    parent_unit_id character(26),
    responsibilities json,
    authorities json,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL,
    CONSTRAINT organization_units_unit_type_check CHECK (((unit_type)::text = ANY ((ARRAY['board_of_commissioners'::character varying, 'board_of_directors'::character varying, 'executive_committee'::character varying, 'audit_committee'::character varying, 'risk_committee'::character varying, 'nomination_committee'::character varying, 'remuneration_committee'::character varying, 'division'::character varying, 'department'::character varying, 'section'::character varying, 'team'::character varying, 'branch_office'::character varying, 'representative_office'::character varying])::text[])))
);


--
-- Name: organizations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organizations (
    id character(26) NOT NULL,
    organization_code character varying(255),
    organization_type character varying(255) DEFAULT 'subsidiary'::character varying NOT NULL,
    parent_organization_id character(26),
    name character varying(255) NOT NULL,
    description text,
    address character varying(255),
    phone character varying(255),
    email character varying(255),
    website character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    registration_number character varying(255),
    tax_number character varying(255),
    governance_structure json,
    authorized_capital numeric(15,2),
    paid_capital numeric(15,2),
    establishment_date date,
    legal_status character varying(255),
    business_activities text,
    contact_persons json,
    level integer DEFAULT 0 NOT NULL,
    path character varying(255),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL,
    CONSTRAINT organizations_organization_type_check CHECK (((organization_type)::text = ANY ((ARRAY['holding_company'::character varying, 'subsidiary'::character varying, 'division'::character varying, 'branch'::character varying, 'department'::character varying, 'unit'::character varying])::text[])))
);


--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.passkeys (
    id character(26) NOT NULL,
    authenticatable_id character(26) NOT NULL,
    name text NOT NULL,
    credential_id text NOT NULL,
    data json NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id character(26) NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) with time zone,
    expires_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: poll_analytics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.poll_analytics (
    id character(26) NOT NULL,
    poll_id character(26) NOT NULL,
    encrypted_results_summary json NOT NULL,
    participation_stats json NOT NULL,
    generated_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: poll_options; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.poll_options (
    id character(26) NOT NULL,
    poll_id character(26) NOT NULL,
    option_order integer NOT NULL,
    encrypted_option_text text NOT NULL,
    option_hash character varying(255) NOT NULL,
    option_type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    encrypted_metadata text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: poll_votes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.poll_votes (
    id character(26) NOT NULL,
    poll_id character(26) NOT NULL,
    voter_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    encrypted_vote_data json NOT NULL,
    vote_hash character varying(255) NOT NULL,
    vote_encryption_keys json NOT NULL,
    is_anonymous boolean DEFAULT false NOT NULL,
    encrypted_reasoning text,
    voted_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: polls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.polls (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    creator_id character(26) NOT NULL,
    poll_type character varying(255) DEFAULT 'single_choice'::character varying NOT NULL,
    encrypted_question text NOT NULL,
    question_hash character varying(255) NOT NULL,
    encrypted_options json NOT NULL,
    option_hashes json NOT NULL,
    anonymous boolean DEFAULT false NOT NULL,
    allow_multiple_votes boolean DEFAULT false NOT NULL,
    show_results_immediately boolean DEFAULT true NOT NULL,
    expires_at timestamp(0) without time zone,
    is_closed boolean DEFAULT false NOT NULL,
    closed_at timestamp(0) without time zone,
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: quantum_key_encapsulation; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quantum_key_encapsulation (
    id character(26) NOT NULL,
    session_id character(26) NOT NULL,
    algorithm character varying(255) NOT NULL,
    public_key text NOT NULL,
    private_key_encrypted text NOT NULL,
    shared_secret_encrypted text,
    ciphertext text,
    is_active boolean DEFAULT true NOT NULL,
    established_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: rate_limit_configs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rate_limit_configs (
    id character(26) NOT NULL,
    action_name character varying(255) NOT NULL,
    scope character varying(255) NOT NULL,
    max_attempts integer NOT NULL,
    window_seconds integer NOT NULL,
    penalty_duration_seconds integer,
    escalation_rules json,
    is_active boolean DEFAULT true NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: rate_limits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rate_limits (
    id character(26) NOT NULL,
    key character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    hits integer DEFAULT 1 NOT NULL,
    max_attempts integer NOT NULL,
    window_start timestamp(0) without time zone NOT NULL,
    window_end timestamp(0) without time zone NOT NULL,
    reset_at timestamp(0) without time zone,
    is_blocked boolean DEFAULT false NOT NULL,
    metadata text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ref_geo_city; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_geo_city (
    id character(26) NOT NULL,
    province_id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: ref_geo_country; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_geo_country (
    id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    iso_code character varying(3),
    phone_code character varying(255),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: ref_geo_district; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_geo_district (
    id character(26) NOT NULL,
    city_id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: ref_geo_province; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_geo_province (
    id character(26) NOT NULL,
    country_id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: ref_geo_village; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ref_geo_village (
    id character(26) NOT NULL,
    district_id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: scheduled_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scheduled_messages (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    sender_id character(26) NOT NULL,
    content text NOT NULL,
    content_type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    scheduled_for timestamp(0) without time zone NOT NULL,
    timezone character varying(50) DEFAULT 'UTC'::character varying NOT NULL,
    status character varying(255) NOT NULL,
    retry_count integer DEFAULT 0 NOT NULL,
    max_retries integer DEFAULT 3 NOT NULL,
    error_message text,
    sent_message_id character(26),
    sent_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: security_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.security_audit_logs (
    id character(26) NOT NULL,
    event_type character varying(255) NOT NULL,
    severity character varying(255) DEFAULT 'info'::character varying NOT NULL,
    user_id character(26),
    device_id character(26),
    conversation_id character(26),
    ip_address character varying(255),
    user_agent text,
    location json,
    metadata json,
    risk_score integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    resolved_at timestamp(0) without time zone,
    resolved_by character(26),
    organization_id character(26),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT security_audit_logs_severity_check CHECK (((severity)::text = ANY ((ARRAY['info'::character varying, 'low'::character varying, 'medium'::character varying, 'high'::character varying, 'critical'::character varying])::text[]))),
    CONSTRAINT security_audit_logs_status_check CHECK (((status)::text = ANY ((ARRAY['normal'::character varying, 'pending'::character varying, 'investigating'::character varying, 'resolved'::character varying, 'false_positive'::character varying])::text[])))
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id character(26),
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: signal_identity_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signal_identity_keys (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    public_key text NOT NULL,
    private_key_encrypted text NOT NULL,
    key_fingerprint text NOT NULL,
    generated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    registration_id integer NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    quantum_public_key text,
    quantum_private_key_encrypted text,
    quantum_algorithm character varying(255),
    is_quantum_capable boolean DEFAULT false NOT NULL,
    quantum_version integer DEFAULT 1 NOT NULL
);


--
-- Name: signal_one_time_prekeys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signal_one_time_prekeys (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    key_id integer NOT NULL,
    public_key text NOT NULL,
    private_key_encrypted text NOT NULL,
    used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: signal_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signal_sessions (
    id character(26) NOT NULL,
    local_user_id character(26) NOT NULL,
    local_device_id character(26) NOT NULL,
    remote_user_id character(26) NOT NULL,
    remote_device_id character(26) NOT NULL,
    session_state_encrypted text NOT NULL,
    remote_identity_key text NOT NULL,
    message_counter integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    last_used_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: signal_signed_prekeys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signal_signed_prekeys (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    key_id integer NOT NULL,
    public_key text NOT NULL,
    private_key_encrypted text NOT NULL,
    signature text NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: snapshots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.snapshots (
    id character(26) NOT NULL,
    aggregate_ulid character(26) NOT NULL,
    aggregate_version bigint NOT NULL,
    state jsonb NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: spam_detections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.spam_detections (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    detection_type character varying(255) NOT NULL,
    confidence_score real NOT NULL,
    detection_details json NOT NULL,
    action_taken character varying(255) NOT NULL,
    is_false_positive boolean DEFAULT false NOT NULL,
    review_notes text,
    reviewed_by character(26),
    reviewed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stored_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stored_events (
    id character(26) NOT NULL,
    aggregate_ulid character(26),
    aggregate_version bigint,
    event_version smallint DEFAULT '1'::smallint NOT NULL,
    event_class character varying(255) NOT NULL,
    event_properties jsonb NOT NULL,
    meta_data jsonb NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: survey_question_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_question_responses (
    id character(26) NOT NULL,
    survey_response_id character(26) NOT NULL,
    question_id character(26) NOT NULL,
    encrypted_answer text NOT NULL,
    answer_hash character varying(255) NOT NULL,
    answered_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_questions (
    id character(26) NOT NULL,
    survey_id character(26) NOT NULL,
    question_order integer NOT NULL,
    question_type character varying(255) NOT NULL,
    encrypted_question_text text NOT NULL,
    question_hash character varying(255) NOT NULL,
    required boolean DEFAULT false NOT NULL,
    encrypted_options json,
    option_hashes json,
    validation_rules json,
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_responses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.survey_responses (
    id character(26) NOT NULL,
    survey_id character(26) NOT NULL,
    respondent_id character(26) NOT NULL,
    device_id character(26) NOT NULL,
    is_complete boolean DEFAULT false NOT NULL,
    is_anonymous boolean DEFAULT false NOT NULL,
    started_at timestamp(0) without time zone NOT NULL,
    completed_at timestamp(0) without time zone,
    response_encryption_keys json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: surveys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.surveys (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    creator_id character(26) NOT NULL,
    encrypted_title text NOT NULL,
    title_hash character varying(255) NOT NULL,
    encrypted_description text,
    description_hash character varying(255),
    anonymous boolean DEFAULT false NOT NULL,
    allow_partial_responses boolean DEFAULT true NOT NULL,
    randomize_questions boolean DEFAULT false NOT NULL,
    expires_at timestamp(0) without time zone,
    is_closed boolean DEFAULT false NOT NULL,
    closed_at timestamp(0) without time zone,
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: suspicious_activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suspicious_activities (
    id character(26) NOT NULL,
    user_id character(26),
    activity_type character varying(255) NOT NULL,
    activity_description text NOT NULL,
    activity_data json NOT NULL,
    severity_score integer DEFAULT 1 NOT NULL,
    detection_method character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    investigation_notes text,
    investigated_by character(26),
    investigated_at timestamp(0) without time zone,
    client_ip character varying(255),
    user_agent character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sys_model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_model_has_permissions (
    permission_id character(26) NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id character(26) NOT NULL,
    team_id character(26)
);


--
-- Name: sys_model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_model_has_roles (
    role_id character(26) NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id character(26) NOT NULL,
    team_id character(26) NOT NULL
);


--
-- Name: sys_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_permissions (
    id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: sys_role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_role_has_permissions (
    permission_id character(26) NOT NULL,
    role_id character(26) NOT NULL
);


--
-- Name: sys_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_roles (
    id character(26) NOT NULL,
    team_id character(26),
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26) NOT NULL,
    updated_by character(26) NOT NULL
);


--
-- Name: sys_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_users (
    id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_by character(26),
    updated_by character(26),
    avatar character varying(255),
    username character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    street_address character varying(255),
    locality character varying(255),
    region character varying(255),
    postal_code character varying(255),
    country character varying(255),
    formatted_address text,
    phone_number character varying(255),
    middle_name character varying(255),
    nickname character varying(255),
    profile_url character varying(255),
    website character varying(255),
    gender character varying(255),
    birthdate date,
    zoneinfo character varying(255),
    locale character varying(255),
    phone_verified_at timestamp(0) without time zone,
    profile_updated_at timestamp(0) without time zone,
    external_id character varying(255),
    social_links json
);


--
-- Name: user_consents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_consents (
    id bigint NOT NULL,
    user_id character(26) NOT NULL,
    client_id character(26) NOT NULL,
    scopes json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    scope_details json NOT NULL,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    granted_by_ip character varying(255),
    granted_user_agent text,
    usage_stats json,
    CONSTRAINT user_consents_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'revoked'::character varying, 'expired'::character varying])::text[])))
);


--
-- Name: user_consents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_consents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_consents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_consents_id_seq OWNED BY public.user_consents.id;


--
-- Name: user_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_devices (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    device_name character varying(255) NOT NULL,
    device_type character varying(255) DEFAULT 'unknown'::character varying NOT NULL,
    public_identity_key text,
    device_info text,
    last_seen_at timestamp(0) without time zone,
    is_trusted boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    registered_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_used_at timestamp(0) without time zone,
    device_fingerprint character varying(255) NOT NULL,
    hardware_fingerprint character varying(255),
    platform character varying(100),
    user_agent character varying(500),
    public_key text NOT NULL,
    device_capabilities json,
    encryption_capabilities json,
    quantum_ready boolean DEFAULT false NOT NULL,
    security_level character varying(50) DEFAULT 'standard'::character varying NOT NULL,
    encryption_version integer DEFAULT 2 NOT NULL,
    failed_auth_attempts integer DEFAULT 0 NOT NULL,
    quantum_health_score integer DEFAULT 100 NOT NULL,
    trust_level character varying(50),
    verified_at timestamp(0) without time zone,
    auto_trust_expires_at timestamp(0) without time zone,
    last_key_rotation_at timestamp(0) without time zone,
    capabilities_verified_at timestamp(0) without time zone,
    last_quantum_health_check timestamp(0) without time zone,
    locked_until timestamp(0) without time zone,
    revoked_at timestamp(0) without time zone,
    revocation_reason text,
    preferred_algorithm character varying(100)
);


--
-- Name: user_mfa_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_mfa_settings (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    totp_enabled boolean DEFAULT false NOT NULL,
    totp_secret character varying(255),
    totp_confirmed_at timestamp(0) with time zone,
    backup_codes json,
    backup_codes_used integer DEFAULT 0 NOT NULL,
    mfa_required boolean DEFAULT false NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: user_penalties; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_penalties (
    id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    penalty_type character varying(255) NOT NULL,
    reason character varying(255) NOT NULL,
    description text,
    restrictions json NOT NULL,
    severity_level integer DEFAULT 1 NOT NULL,
    starts_at timestamp(0) without time zone NOT NULL,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    applied_by character(26),
    admin_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_call_e2ee_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_call_e2ee_logs (
    id character(26) NOT NULL,
    video_call_id character(26) NOT NULL,
    participant_id character(26) NOT NULL,
    key_operation character varying(255) NOT NULL,
    encryption_algorithm character varying(255) NOT NULL,
    key_id character varying(255) NOT NULL,
    operation_timestamp timestamp(0) without time zone NOT NULL,
    operation_success boolean DEFAULT true NOT NULL,
    error_details text,
    operation_metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_call_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_call_events (
    id character(26) NOT NULL,
    video_call_id character(26) NOT NULL,
    user_id character(26),
    event_type character varying(255) NOT NULL,
    event_data json NOT NULL,
    event_timestamp timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_call_participants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_call_participants (
    id character(26) NOT NULL,
    video_call_id character(26) NOT NULL,
    user_id character(26) NOT NULL,
    participant_identity character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'invited'::character varying NOT NULL,
    invited_at timestamp(0) without time zone NOT NULL,
    joined_at timestamp(0) without time zone,
    left_at timestamp(0) without time zone,
    duration_seconds integer,
    connection_quality json,
    media_tracks json,
    device_info character varying(255),
    rejection_reason character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_call_quality_metrics; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_call_quality_metrics (
    id character(26) NOT NULL,
    video_call_id character(26) NOT NULL,
    participant_id character(26) NOT NULL,
    measured_at timestamp(0) without time zone NOT NULL,
    video_metrics json,
    audio_metrics json,
    connection_metrics json NOT NULL,
    quality_score integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_call_recordings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_call_recordings (
    id character(26) NOT NULL,
    video_call_id character(26) NOT NULL,
    recording_id character varying(255) NOT NULL,
    storage_type character varying(255) NOT NULL,
    file_path text NOT NULL,
    file_format character varying(255) NOT NULL,
    file_size bigint,
    duration_seconds integer,
    recording_metadata json,
    is_encrypted boolean DEFAULT false NOT NULL,
    encryption_key_id character varying(255),
    recording_started_at timestamp(0) without time zone NOT NULL,
    recording_ended_at timestamp(0) without time zone,
    processing_status character varying(255) DEFAULT 'processing'::character varying NOT NULL,
    processing_error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: video_calls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.video_calls (
    id character(26) NOT NULL,
    conversation_id character(26) NOT NULL,
    initiated_by character(26) NOT NULL,
    livekit_room_name character varying(255) NOT NULL,
    call_type character varying(255) DEFAULT 'video'::character varying NOT NULL,
    status character varying(255) DEFAULT 'initiated'::character varying NOT NULL,
    started_at timestamp(0) without time zone,
    ended_at timestamp(0) without time zone,
    duration_seconds integer,
    participants json NOT NULL,
    e2ee_settings json,
    quality_settings character varying(255),
    metadata json,
    is_recorded boolean DEFAULT false NOT NULL,
    recording_url text,
    failure_reason character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: voice_transcriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.voice_transcriptions (
    id character(26) NOT NULL,
    message_id character(26) NOT NULL,
    attachment_id character(26) NOT NULL,
    transcript text,
    language character varying(10),
    confidence double precision,
    duration double precision,
    word_count integer,
    segments json,
    status character varying(255) NOT NULL,
    provider character varying(255) DEFAULT 'openai-whisper'::character varying NOT NULL,
    error_message text,
    retry_count integer DEFAULT 0 NOT NULL,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: webhook_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_deliveries (
    id character(26) NOT NULL,
    webhook_id character(26) NOT NULL,
    event_type character varying(255) NOT NULL,
    payload json NOT NULL,
    headers json,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    http_status integer,
    response_body text,
    attempt integer DEFAULT 1 NOT NULL,
    delivered_at timestamp(0) without time zone,
    next_retry_at timestamp(0) without time zone,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT webhook_deliveries_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'success'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: webhooks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhooks (
    id character(26) NOT NULL,
    name character varying(255) NOT NULL,
    url text NOT NULL,
    secret character varying(255),
    events json DEFAULT '[]'::json NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    retry_attempts integer DEFAULT 3 NOT NULL,
    timeout integer DEFAULT 30 NOT NULL,
    headers json,
    organization_id character(26) NOT NULL,
    created_by character(26) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT webhooks_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'disabled'::character varying])::text[])))
);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: oauth_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.oauth_audit_logs_id_seq'::regclass);


--
-- Name: oauth_scopes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_scopes ALTER COLUMN id SET DEFAULT nextval('public.oauth_scopes_id_seq'::regclass);


--
-- Name: user_consents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_consents ALTER COLUMN id SET DEFAULT nextval('public.user_consents_id_seq'::regclass);


--
-- Name: abuse_reports abuse_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_pkey PRIMARY KEY (id);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: backup_restorations backup_restorations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.backup_restorations
    ADD CONSTRAINT backup_restorations_pkey PRIMARY KEY (id);


--
-- Name: backup_verification backup_verification_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.backup_verification
    ADD CONSTRAINT backup_verification_pkey PRIMARY KEY (id);


--
-- Name: bot_conversations bot_conversations_bot_id_conversation_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_conversations
    ADD CONSTRAINT bot_conversations_bot_id_conversation_id_unique UNIQUE (bot_id, conversation_id);


--
-- Name: bot_conversations bot_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_conversations
    ADD CONSTRAINT bot_conversations_pkey PRIMARY KEY (id);


--
-- Name: bot_encryption_keys bot_encryption_keys_bot_id_conversation_id_key_pair_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_encryption_keys
    ADD CONSTRAINT bot_encryption_keys_bot_id_conversation_id_key_pair_id_unique UNIQUE (bot_id, conversation_id, key_pair_id);


--
-- Name: bot_encryption_keys bot_encryption_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_encryption_keys
    ADD CONSTRAINT bot_encryption_keys_pkey PRIMARY KEY (id);


--
-- Name: bot_messages bot_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_messages
    ADD CONSTRAINT bot_messages_pkey PRIMARY KEY (id);


--
-- Name: bots bots_api_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bots
    ADD CONSTRAINT bots_api_token_unique UNIQUE (api_token);


--
-- Name: bots bots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bots
    ADD CONSTRAINT bots_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: chat_backups chat_backups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_backups
    ADD CONSTRAINT chat_backups_pkey PRIMARY KEY (id);


--
-- Name: chat_conversations chat_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_conversations
    ADD CONSTRAINT chat_conversations_pkey PRIMARY KEY (id);


--
-- Name: chat_encryption_keys chat_encryption_keys_conversation_id_user_id_device_id_key_vers; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_conversation_id_user_id_device_id_key_vers UNIQUE (conversation_id, user_id, device_id, key_version);


--
-- Name: chat_encryption_keys chat_encryption_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_pkey PRIMARY KEY (id);


--
-- Name: chat_messages chat_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_pkey PRIMARY KEY (id);


--
-- Name: chat_thread_participants chat_thread_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_thread_participants
    ADD CONSTRAINT chat_thread_participants_pkey PRIMARY KEY (id);


--
-- Name: chat_thread_participants chat_thread_participants_thread_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_thread_participants
    ADD CONSTRAINT chat_thread_participants_thread_id_user_id_unique UNIQUE (thread_id, user_id);


--
-- Name: chat_threads chat_threads_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_threads
    ADD CONSTRAINT chat_threads_pkey PRIMARY KEY (id);


--
-- Name: conversation_key_bundles conversation_key_bundles_conversation_id_participant_id_key_ver; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_key_bundles
    ADD CONSTRAINT conversation_key_bundles_conversation_id_participant_id_key_ver UNIQUE (conversation_id, participant_id, key_version);


--
-- Name: conversation_key_bundles conversation_key_bundles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_key_bundles
    ADD CONSTRAINT conversation_key_bundles_pkey PRIMARY KEY (id);


--
-- Name: conversation_participants conversation_participants_conversation_id_user_id_device_id_uni; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_conversation_id_user_id_device_id_uni UNIQUE (conversation_id, user_id, device_id);


--
-- Name: conversation_participants conversation_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_pkey PRIMARY KEY (id);


--
-- Name: export_templates export_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.export_templates
    ADD CONSTRAINT export_templates_pkey PRIMARY KEY (id);


--
-- Name: export_templates export_templates_user_id_template_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.export_templates
    ADD CONSTRAINT export_templates_user_id_template_name_unique UNIQUE (user_id, template_name);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: ip_restrictions ip_restrictions_ip_address_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ip_restrictions
    ADD CONSTRAINT ip_restrictions_ip_address_unique UNIQUE (ip_address);


--
-- Name: ip_restrictions ip_restrictions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ip_restrictions
    ADD CONSTRAINT ip_restrictions_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: message_attachments message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_pkey PRIMARY KEY (id);


--
-- Name: message_delivery_receipts message_delivery_receipts_message_id_recipient_user_id_recipien; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_delivery_receipts
    ADD CONSTRAINT message_delivery_receipts_message_id_recipient_user_id_recipien UNIQUE (message_id, recipient_user_id, recipient_device_id);


--
-- Name: message_delivery_receipts message_delivery_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_delivery_receipts
    ADD CONSTRAINT message_delivery_receipts_pkey PRIMARY KEY (id);


--
-- Name: message_files message_files_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_files
    ADD CONSTRAINT message_files_pkey PRIMARY KEY (id);


--
-- Name: message_mentions message_mentions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_mentions
    ADD CONSTRAINT message_mentions_pkey PRIMARY KEY (id);


--
-- Name: message_poll_votes message_poll_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_poll_votes
    ADD CONSTRAINT message_poll_votes_pkey PRIMARY KEY (id);


--
-- Name: message_poll_votes message_poll_votes_poll_id_voter_user_id_voter_device_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_poll_votes
    ADD CONSTRAINT message_poll_votes_poll_id_voter_user_id_voter_device_id_unique UNIQUE (poll_id, voter_user_id, voter_device_id);


--
-- Name: message_polls message_polls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_polls
    ADD CONSTRAINT message_polls_pkey PRIMARY KEY (id);


--
-- Name: message_reactions message_reactions_message_id_user_id_device_id_reaction_type_un; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_message_id_user_id_device_id_reaction_type_un UNIQUE (message_id, user_id, device_id, reaction_type);


--
-- Name: message_reactions message_reactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: mobile_pass_devices mobile_pass_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_pass_devices
    ADD CONSTRAINT mobile_pass_devices_pkey PRIMARY KEY (id);


--
-- Name: mobile_pass_registrations mobile_pass_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_pass_registrations
    ADD CONSTRAINT mobile_pass_registrations_pkey PRIMARY KEY (id);


--
-- Name: mobile_passes mobile_passes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_passes
    ADD CONSTRAINT mobile_passes_pkey PRIMARY KEY (id);


--
-- Name: sys_model_has_permissions model_has_permissions_permission_model_type_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_model_type_primary UNIQUE (team_id, permission_id, model_id, model_type);


--
-- Name: oauth_access_tokens oauth_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_access_tokens
    ADD CONSTRAINT oauth_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: oauth_audit_logs oauth_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_audit_logs
    ADD CONSTRAINT oauth_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: oauth_auth_codes oauth_auth_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_auth_codes
    ADD CONSTRAINT oauth_auth_codes_pkey PRIMARY KEY (id);


--
-- Name: oauth_clients oauth_clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_clients
    ADD CONSTRAINT oauth_clients_pkey PRIMARY KEY (id);


--
-- Name: oauth_refresh_tokens oauth_refresh_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_refresh_tokens
    ADD CONSTRAINT oauth_refresh_tokens_pkey PRIMARY KEY (id);


--
-- Name: oauth_scopes oauth_scopes_identifier_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_scopes
    ADD CONSTRAINT oauth_scopes_identifier_unique UNIQUE (identifier);


--
-- Name: oauth_scopes oauth_scopes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_scopes
    ADD CONSTRAINT oauth_scopes_pkey PRIMARY KEY (id);


--
-- Name: one_time_passwords one_time_passwords_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_passwords
    ADD CONSTRAINT one_time_passwords_pkey PRIMARY KEY (id);


--
-- Name: organization_memberships organization_memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_pkey PRIMARY KEY (id);


--
-- Name: organization_position_levels organization_position_levels_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_position_levels
    ADD CONSTRAINT organization_position_levels_code_unique UNIQUE (code);


--
-- Name: organization_position_levels organization_position_levels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_position_levels
    ADD CONSTRAINT organization_position_levels_pkey PRIMARY KEY (id);


--
-- Name: organization_positions organization_positions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_pkey PRIMARY KEY (id);


--
-- Name: organization_positions organization_positions_position_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_position_code_unique UNIQUE (position_code);


--
-- Name: organization_units organization_units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_pkey PRIMARY KEY (id);


--
-- Name: organization_units organization_units_unit_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_unit_code_unique UNIQUE (unit_code);


--
-- Name: organizations organizations_organization_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_organization_code_unique UNIQUE (organization_code);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: poll_analytics poll_analytics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_analytics
    ADD CONSTRAINT poll_analytics_pkey PRIMARY KEY (id);


--
-- Name: poll_options poll_options_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_pkey PRIMARY KEY (id);


--
-- Name: poll_options poll_options_poll_id_option_order_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_poll_id_option_order_unique UNIQUE (poll_id, option_order);


--
-- Name: poll_votes poll_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_pkey PRIMARY KEY (id);


--
-- Name: poll_votes poll_votes_poll_id_voter_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_poll_id_voter_id_unique UNIQUE (poll_id, voter_id);


--
-- Name: polls polls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polls
    ADD CONSTRAINT polls_pkey PRIMARY KEY (id);


--
-- Name: quantum_key_encapsulation quantum_key_encapsulation_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quantum_key_encapsulation
    ADD CONSTRAINT quantum_key_encapsulation_pkey PRIMARY KEY (id);


--
-- Name: rate_limit_configs rate_limit_configs_action_name_scope_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rate_limit_configs
    ADD CONSTRAINT rate_limit_configs_action_name_scope_unique UNIQUE (action_name, scope);


--
-- Name: rate_limit_configs rate_limit_configs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rate_limit_configs
    ADD CONSTRAINT rate_limit_configs_pkey PRIMARY KEY (id);


--
-- Name: rate_limits rate_limits_key_action_window_start_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rate_limits
    ADD CONSTRAINT rate_limits_key_action_window_start_unique UNIQUE (key, action, window_start);


--
-- Name: rate_limits rate_limits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rate_limits
    ADD CONSTRAINT rate_limits_pkey PRIMARY KEY (id);


--
-- Name: ref_geo_city ref_geo_city_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_city
    ADD CONSTRAINT ref_geo_city_pkey PRIMARY KEY (id);


--
-- Name: ref_geo_city ref_geo_city_province_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_city
    ADD CONSTRAINT ref_geo_city_province_id_code_unique UNIQUE (province_id, code);


--
-- Name: ref_geo_country ref_geo_country_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_country
    ADD CONSTRAINT ref_geo_country_code_unique UNIQUE (code);


--
-- Name: ref_geo_country ref_geo_country_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_country
    ADD CONSTRAINT ref_geo_country_iso_code_unique UNIQUE (iso_code);


--
-- Name: ref_geo_country ref_geo_country_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_country
    ADD CONSTRAINT ref_geo_country_pkey PRIMARY KEY (id);


--
-- Name: ref_geo_district ref_geo_district_city_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_district
    ADD CONSTRAINT ref_geo_district_city_id_code_unique UNIQUE (city_id, code);


--
-- Name: ref_geo_district ref_geo_district_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_district
    ADD CONSTRAINT ref_geo_district_pkey PRIMARY KEY (id);


--
-- Name: ref_geo_province ref_geo_province_country_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_province
    ADD CONSTRAINT ref_geo_province_country_id_code_unique UNIQUE (country_id, code);


--
-- Name: ref_geo_province ref_geo_province_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_province
    ADD CONSTRAINT ref_geo_province_pkey PRIMARY KEY (id);


--
-- Name: ref_geo_village ref_geo_village_district_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_village
    ADD CONSTRAINT ref_geo_village_district_id_code_unique UNIQUE (district_id, code);


--
-- Name: ref_geo_village ref_geo_village_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_village
    ADD CONSTRAINT ref_geo_village_pkey PRIMARY KEY (id);


--
-- Name: scheduled_messages scheduled_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scheduled_messages
    ADD CONSTRAINT scheduled_messages_pkey PRIMARY KEY (id);


--
-- Name: security_audit_logs security_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: signal_identity_keys signal_identity_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_identity_keys
    ADD CONSTRAINT signal_identity_keys_pkey PRIMARY KEY (id);


--
-- Name: signal_identity_keys signal_identity_keys_user_id_device_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_identity_keys
    ADD CONSTRAINT signal_identity_keys_user_id_device_id_unique UNIQUE (user_id, device_id);


--
-- Name: signal_one_time_prekeys signal_one_time_prekeys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_one_time_prekeys
    ADD CONSTRAINT signal_one_time_prekeys_pkey PRIMARY KEY (id);


--
-- Name: signal_one_time_prekeys signal_one_time_prekeys_user_id_device_id_key_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_one_time_prekeys
    ADD CONSTRAINT signal_one_time_prekeys_user_id_device_id_key_id_unique UNIQUE (user_id, device_id, key_id);


--
-- Name: signal_sessions signal_sessions_local_user_id_local_device_id_remote_user_id_re; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_local_user_id_local_device_id_remote_user_id_re UNIQUE (local_user_id, local_device_id, remote_user_id, remote_device_id);


--
-- Name: signal_sessions signal_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_pkey PRIMARY KEY (id);


--
-- Name: signal_signed_prekeys signal_signed_prekeys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_signed_prekeys
    ADD CONSTRAINT signal_signed_prekeys_pkey PRIMARY KEY (id);


--
-- Name: signal_signed_prekeys signal_signed_prekeys_user_id_device_id_key_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_signed_prekeys
    ADD CONSTRAINT signal_signed_prekeys_user_id_device_id_key_id_unique UNIQUE (user_id, device_id, key_id);


--
-- Name: snapshots snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.snapshots
    ADD CONSTRAINT snapshots_pkey PRIMARY KEY (id);


--
-- Name: spam_detections spam_detections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_detections
    ADD CONSTRAINT spam_detections_pkey PRIMARY KEY (id);


--
-- Name: stored_events stored_events_aggregate_ulid_aggregate_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_events
    ADD CONSTRAINT stored_events_aggregate_ulid_aggregate_version_unique UNIQUE (aggregate_ulid, aggregate_version);


--
-- Name: stored_events stored_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stored_events
    ADD CONSTRAINT stored_events_pkey PRIMARY KEY (id);


--
-- Name: survey_question_responses survey_question_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_question_responses
    ADD CONSTRAINT survey_question_responses_pkey PRIMARY KEY (id);


--
-- Name: survey_question_responses survey_question_responses_survey_response_id_question_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_question_responses
    ADD CONSTRAINT survey_question_responses_survey_response_id_question_id_unique UNIQUE (survey_response_id, question_id);


--
-- Name: survey_questions survey_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_pkey PRIMARY KEY (id);


--
-- Name: survey_questions survey_questions_survey_id_question_order_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_survey_id_question_order_unique UNIQUE (survey_id, question_order);


--
-- Name: survey_responses survey_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_pkey PRIMARY KEY (id);


--
-- Name: survey_responses survey_responses_survey_id_respondent_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_id_respondent_id_unique UNIQUE (survey_id, respondent_id);


--
-- Name: surveys surveys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_pkey PRIMARY KEY (id);


--
-- Name: suspicious_activities suspicious_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suspicious_activities
    ADD CONSTRAINT suspicious_activities_pkey PRIMARY KEY (id);


--
-- Name: sys_model_has_roles sys_model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_model_has_roles
    ADD CONSTRAINT sys_model_has_roles_pkey PRIMARY KEY (team_id, role_id, model_id, model_type);


--
-- Name: sys_permissions sys_permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_permissions
    ADD CONSTRAINT sys_permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: sys_permissions sys_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_permissions
    ADD CONSTRAINT sys_permissions_pkey PRIMARY KEY (id);


--
-- Name: sys_role_has_permissions sys_role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_role_has_permissions
    ADD CONSTRAINT sys_role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: sys_roles sys_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_roles
    ADD CONSTRAINT sys_roles_pkey PRIMARY KEY (id);


--
-- Name: sys_roles sys_roles_team_id_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_roles
    ADD CONSTRAINT sys_roles_team_id_name_guard_name_unique UNIQUE (team_id, name, guard_name);


--
-- Name: sys_users sys_users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_email_unique UNIQUE (email);


--
-- Name: sys_users sys_users_external_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_external_id_unique UNIQUE (external_id);


--
-- Name: sys_users sys_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_pkey PRIMARY KEY (id);


--
-- Name: sys_users sys_users_username_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_username_unique UNIQUE (username);


--
-- Name: organization_memberships unique_user_org_position; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT unique_user_org_position UNIQUE (user_id, organization_id, organization_position_id);


--
-- Name: user_consents user_consents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_consents
    ADD CONSTRAINT user_consents_pkey PRIMARY KEY (id);


--
-- Name: user_consents user_consents_user_id_client_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_consents
    ADD CONSTRAINT user_consents_user_id_client_id_unique UNIQUE (user_id, client_id);


--
-- Name: user_devices user_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_devices
    ADD CONSTRAINT user_devices_pkey PRIMARY KEY (id);


--
-- Name: user_mfa_settings user_mfa_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_mfa_settings
    ADD CONSTRAINT user_mfa_settings_pkey PRIMARY KEY (id);


--
-- Name: user_mfa_settings user_mfa_settings_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_mfa_settings
    ADD CONSTRAINT user_mfa_settings_user_id_unique UNIQUE (user_id);


--
-- Name: user_penalties user_penalties_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_penalties
    ADD CONSTRAINT user_penalties_pkey PRIMARY KEY (id);


--
-- Name: video_call_e2ee_logs video_call_e2ee_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_e2ee_logs
    ADD CONSTRAINT video_call_e2ee_logs_pkey PRIMARY KEY (id);


--
-- Name: video_call_events video_call_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_events
    ADD CONSTRAINT video_call_events_pkey PRIMARY KEY (id);


--
-- Name: video_call_participants video_call_participants_participant_identity_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_participants
    ADD CONSTRAINT video_call_participants_participant_identity_unique UNIQUE (participant_identity);


--
-- Name: video_call_participants video_call_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_participants
    ADD CONSTRAINT video_call_participants_pkey PRIMARY KEY (id);


--
-- Name: video_call_participants video_call_participants_video_call_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_participants
    ADD CONSTRAINT video_call_participants_video_call_id_user_id_unique UNIQUE (video_call_id, user_id);


--
-- Name: video_call_quality_metrics video_call_quality_metrics_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_quality_metrics
    ADD CONSTRAINT video_call_quality_metrics_pkey PRIMARY KEY (id);


--
-- Name: video_call_recordings video_call_recordings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_recordings
    ADD CONSTRAINT video_call_recordings_pkey PRIMARY KEY (id);


--
-- Name: video_calls video_calls_livekit_room_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_calls
    ADD CONSTRAINT video_calls_livekit_room_name_unique UNIQUE (livekit_room_name);


--
-- Name: video_calls video_calls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_calls
    ADD CONSTRAINT video_calls_pkey PRIMARY KEY (id);


--
-- Name: voice_transcriptions voice_transcriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voice_transcriptions
    ADD CONSTRAINT voice_transcriptions_pkey PRIMARY KEY (id);


--
-- Name: webhook_deliveries webhook_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_pkey PRIMARY KEY (id);


--
-- Name: webhooks webhooks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_pkey PRIMARY KEY (id);


--
-- Name: abuse_reports_abuse_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX abuse_reports_abuse_type_status_index ON public.abuse_reports USING btree (abuse_type, status);


--
-- Name: abuse_reports_reported_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX abuse_reports_reported_user_id_status_index ON public.abuse_reports USING btree (reported_user_id, status);


--
-- Name: abuse_reports_reporter_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX abuse_reports_reporter_user_id_created_at_index ON public.abuse_reports USING btree (reporter_user_id, created_at);


--
-- Name: abuse_reports_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX abuse_reports_status_created_at_index ON public.abuse_reports USING btree (status, created_at);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: activity_log_log_name_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_organization_id_index ON public.activity_log USING btree (log_name, organization_id);


--
-- Name: activity_log_organization_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_organization_id_created_at_index ON public.activity_log USING btree (organization_id, created_at);


--
-- Name: activity_log_tenant_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_tenant_id_created_at_index ON public.activity_log USING btree (tenant_id, created_at);


--
-- Name: backup_restorations_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX backup_restorations_status_created_at_index ON public.backup_restorations USING btree (status, created_at);


--
-- Name: backup_restorations_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX backup_restorations_user_id_status_index ON public.backup_restorations USING btree (user_id, status);


--
-- Name: backup_verification_backup_id_verification_method_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX backup_verification_backup_id_verification_method_index ON public.backup_verification USING btree (backup_id, verification_method);


--
-- Name: bot_conversations_bot_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_conversations_bot_id_status_index ON public.bot_conversations USING btree (bot_id, status);


--
-- Name: bot_conversations_conversation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_conversations_conversation_id_status_index ON public.bot_conversations USING btree (conversation_id, status);


--
-- Name: bot_encryption_keys_algorithm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_encryption_keys_algorithm_index ON public.bot_encryption_keys USING btree (algorithm);


--
-- Name: bot_encryption_keys_bot_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_encryption_keys_bot_id_is_active_index ON public.bot_encryption_keys USING btree (bot_id, is_active);


--
-- Name: bot_encryption_keys_conversation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_encryption_keys_conversation_id_is_active_index ON public.bot_encryption_keys USING btree (conversation_id, is_active);


--
-- Name: bot_encryption_keys_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_encryption_keys_expires_at_index ON public.bot_encryption_keys USING btree (expires_at);


--
-- Name: bot_messages_bot_id_direction_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_messages_bot_id_direction_created_at_index ON public.bot_messages USING btree (bot_id, direction, created_at);


--
-- Name: bot_messages_conversation_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_messages_conversation_id_created_at_index ON public.bot_messages USING btree (conversation_id, created_at);


--
-- Name: bot_messages_processed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bot_messages_processed_at_index ON public.bot_messages USING btree (processed_at);


--
-- Name: bots_api_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bots_api_token_index ON public.bots USING btree (api_token);


--
-- Name: bots_organization_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bots_organization_id_is_active_index ON public.bots USING btree (organization_id, is_active);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: chat_backups_conversation_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_backups_conversation_id_created_at_index ON public.chat_backups USING btree (conversation_id, created_at);


--
-- Name: chat_backups_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_backups_expires_at_index ON public.chat_backups USING btree (expires_at);


--
-- Name: chat_backups_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_backups_status_created_at_index ON public.chat_backups USING btree (status, created_at);


--
-- Name: chat_backups_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_backups_user_id_status_index ON public.chat_backups USING btree (user_id, status);


--
-- Name: chat_conversations_last_activity_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_conversations_last_activity_at_index ON public.chat_conversations USING btree (last_activity_at);


--
-- Name: chat_conversations_organization_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_conversations_organization_id_type_index ON public.chat_conversations USING btree (organization_id, type);


--
-- Name: chat_conversations_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_conversations_type_is_active_index ON public.chat_conversations USING btree (type, is_active);


--
-- Name: chat_encryption_keys_algorithm_key_strength_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_algorithm_key_strength_index ON public.chat_encryption_keys USING btree (algorithm, key_strength);


--
-- Name: chat_encryption_keys_conversation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_conversation_id_is_active_index ON public.chat_encryption_keys USING btree (conversation_id, is_active);


--
-- Name: chat_encryption_keys_device_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_device_id_is_active_index ON public.chat_encryption_keys USING btree (device_id, is_active);


--
-- Name: chat_encryption_keys_expires_at_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_expires_at_is_active_index ON public.chat_encryption_keys USING btree (expires_at, is_active);


--
-- Name: chat_encryption_keys_revoked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_revoked_at_index ON public.chat_encryption_keys USING btree (revoked_at);


--
-- Name: chat_encryption_keys_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_encryption_keys_user_id_is_active_index ON public.chat_encryption_keys USING btree (user_id, is_active);


--
-- Name: chat_messages_conversation_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_conversation_id_created_at_index ON public.chat_messages USING btree (conversation_id, created_at);


--
-- Name: chat_messages_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_expires_at_index ON public.chat_messages USING btree (expires_at);


--
-- Name: chat_messages_message_type_is_deleted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_message_type_is_deleted_index ON public.chat_messages USING btree (message_type, is_deleted);


--
-- Name: chat_messages_sender_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_sender_id_created_at_index ON public.chat_messages USING btree (sender_id, created_at);


--
-- Name: chat_messages_thread_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_messages_thread_id_created_at_index ON public.chat_messages USING btree (thread_id, created_at);


--
-- Name: chat_thread_participants_thread_id_left_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_thread_participants_thread_id_left_at_index ON public.chat_thread_participants USING btree (thread_id, left_at);


--
-- Name: chat_thread_participants_user_id_left_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_thread_participants_user_id_left_at_index ON public.chat_thread_participants USING btree (user_id, left_at);


--
-- Name: chat_threads_conversation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_threads_conversation_id_is_active_index ON public.chat_threads USING btree (conversation_id, is_active);


--
-- Name: chat_threads_creator_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_threads_creator_id_index ON public.chat_threads USING btree (creator_id);


--
-- Name: chat_threads_last_message_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_threads_last_message_at_index ON public.chat_threads USING btree (last_message_at);


--
-- Name: chat_threads_parent_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX chat_threads_parent_message_id_index ON public.chat_threads USING btree (parent_message_id);


--
-- Name: conversation_key_bundles_conversation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_key_bundles_conversation_id_is_active_index ON public.conversation_key_bundles USING btree (conversation_id, is_active);


--
-- Name: conversation_key_bundles_participant_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_key_bundles_participant_id_is_active_index ON public.conversation_key_bundles USING btree (participant_id, is_active);


--
-- Name: conversation_participants_conversation_id_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_participants_conversation_id_role_index ON public.conversation_participants USING btree (conversation_id, role);


--
-- Name: conversation_participants_user_id_left_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX conversation_participants_user_id_left_at_index ON public.conversation_participants USING btree (user_id, left_at);


--
-- Name: export_templates_template_type_is_shared_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX export_templates_template_type_is_shared_index ON public.export_templates USING btree (template_type, is_shared);


--
-- Name: ip_restrictions_expires_at_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ip_restrictions_expires_at_is_active_index ON public.ip_restrictions USING btree (expires_at, is_active);


--
-- Name: ip_restrictions_last_violation_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ip_restrictions_last_violation_at_index ON public.ip_restrictions USING btree (last_violation_at);


--
-- Name: ip_restrictions_restriction_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ip_restrictions_restriction_type_is_active_index ON public.ip_restrictions USING btree (restriction_type, is_active);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: message_attachments_message_id_mime_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_attachments_message_id_mime_type_index ON public.message_attachments USING btree (message_id, mime_type);


--
-- Name: message_delivery_receipts_message_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_delivery_receipts_message_id_status_index ON public.message_delivery_receipts USING btree (message_id, status);


--
-- Name: message_delivery_receipts_recipient_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_delivery_receipts_recipient_user_id_status_index ON public.message_delivery_receipts USING btree (recipient_user_id, status);


--
-- Name: message_files_file_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_files_file_hash_index ON public.message_files USING btree (file_hash);


--
-- Name: message_files_message_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_files_message_id_created_at_index ON public.message_files USING btree (message_id, created_at);


--
-- Name: message_mentions_mentioned_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_mentions_mentioned_user_id_created_at_index ON public.message_mentions USING btree (mentioned_user_id, created_at);


--
-- Name: message_mentions_message_id_mention_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_mentions_message_id_mention_type_index ON public.message_mentions USING btree (message_id, mention_type);


--
-- Name: message_poll_votes_poll_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_poll_votes_poll_id_created_at_index ON public.message_poll_votes USING btree (poll_id, created_at);


--
-- Name: message_polls_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_polls_expires_at_index ON public.message_polls USING btree (expires_at);


--
-- Name: message_polls_message_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_polls_message_id_is_active_index ON public.message_polls USING btree (message_id, is_active);


--
-- Name: message_reactions_message_id_reaction_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_reactions_message_id_reaction_type_index ON public.message_reactions USING btree (message_id, reaction_type);


--
-- Name: mobile_pass_registrations_device_id_pass_serial_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mobile_pass_registrations_device_id_pass_serial_index ON public.mobile_pass_registrations USING btree (device_id, pass_serial);


--
-- Name: mobile_pass_registrations_device_id_pass_type_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mobile_pass_registrations_device_id_pass_type_id_index ON public.mobile_pass_registrations USING btree (device_id, pass_type_id);


--
-- Name: mobile_passes_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mobile_passes_model_type_model_id_index ON public.mobile_passes USING btree (model_type, model_id);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.sys_model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_permissions_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_team_foreign_key_index ON public.sys_model_has_permissions USING btree (team_id);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.sys_model_has_roles USING btree (model_id, model_type);


--
-- Name: model_has_roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_team_foreign_key_index ON public.sys_model_has_roles USING btree (team_id);


--
-- Name: notification_log_items_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_log_items_created_at_index ON public.notification_log_items USING btree (created_at);


--
-- Name: notification_log_items_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_log_items_notifiable_type_notifiable_id_index ON public.notification_log_items USING btree (notifiable_type, notifiable_id);


--
-- Name: oauth_access_tokens_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_access_tokens_user_id_index ON public.oauth_access_tokens USING btree (user_id);


--
-- Name: oauth_audit_logs_client_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_client_id_created_at_index ON public.oauth_audit_logs USING btree (client_id, created_at);


--
-- Name: oauth_audit_logs_event_type_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_event_type_created_at_index ON public.oauth_audit_logs USING btree (event_type, created_at);


--
-- Name: oauth_audit_logs_organization_id_event_type_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_organization_id_event_type_created_at_index ON public.oauth_audit_logs USING btree (organization_id, event_type, created_at);


--
-- Name: oauth_audit_logs_success_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_success_created_at_index ON public.oauth_audit_logs USING btree (success, created_at);


--
-- Name: oauth_audit_logs_tenant_id_event_type_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_tenant_id_event_type_created_at_index ON public.oauth_audit_logs USING btree (tenant_id, event_type, created_at);


--
-- Name: oauth_audit_logs_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_audit_logs_user_id_created_at_index ON public.oauth_audit_logs USING btree (user_id, created_at);


--
-- Name: oauth_auth_codes_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_auth_codes_client_id_index ON public.oauth_auth_codes USING btree (client_id);


--
-- Name: oauth_auth_codes_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_auth_codes_user_id_index ON public.oauth_auth_codes USING btree (user_id);


--
-- Name: oauth_clients_organization_id_revoked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_clients_organization_id_revoked_index ON public.oauth_clients USING btree (organization_id, revoked);


--
-- Name: oauth_clients_owner_type_owner_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_clients_owner_type_owner_id_index ON public.oauth_clients USING btree (owner_type, owner_id);


--
-- Name: oauth_refresh_tokens_access_token_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX oauth_refresh_tokens_access_token_id_index ON public.oauth_refresh_tokens USING btree (access_token_id);


--
-- Name: one_time_passwords_authenticatable_type_authenticatable_id_inde; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX one_time_passwords_authenticatable_type_authenticatable_id_inde ON public.one_time_passwords USING btree (authenticatable_type, authenticatable_id);


--
-- Name: organization_memberships_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_memberships_created_by_index ON public.organization_memberships USING btree (created_by);


--
-- Name: organization_memberships_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_memberships_organization_id_index ON public.organization_memberships USING btree (organization_id);


--
-- Name: organization_memberships_status_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_memberships_status_organization_id_index ON public.organization_memberships USING btree (status, organization_id);


--
-- Name: organization_memberships_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_memberships_updated_by_index ON public.organization_memberships USING btree (updated_by);


--
-- Name: organization_position_levels_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_position_levels_created_by_index ON public.organization_position_levels USING btree (created_by);


--
-- Name: organization_position_levels_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_position_levels_updated_by_index ON public.organization_position_levels USING btree (updated_by);


--
-- Name: organization_positions_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_positions_created_by_index ON public.organization_positions USING btree (created_by);


--
-- Name: organization_positions_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_positions_updated_by_index ON public.organization_positions USING btree (updated_by);


--
-- Name: organization_units_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_units_created_by_index ON public.organization_units USING btree (created_by);


--
-- Name: organization_units_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_units_organization_id_index ON public.organization_units USING btree (organization_id);


--
-- Name: organization_units_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organization_units_updated_by_index ON public.organization_units USING btree (updated_by);


--
-- Name: organizations_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizations_created_by_index ON public.organizations USING btree (created_by);


--
-- Name: organizations_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organizations_updated_by_index ON public.organizations USING btree (updated_by);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: poll_analytics_poll_id_generated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poll_analytics_poll_id_generated_at_index ON public.poll_analytics USING btree (poll_id, generated_at);


--
-- Name: poll_options_poll_id_option_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poll_options_poll_id_option_order_index ON public.poll_options USING btree (poll_id, option_order);


--
-- Name: poll_votes_poll_id_voted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poll_votes_poll_id_voted_at_index ON public.poll_votes USING btree (poll_id, voted_at);


--
-- Name: poll_votes_voter_id_voted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX poll_votes_voter_id_voted_at_index ON public.poll_votes USING btree (voter_id, voted_at);


--
-- Name: polls_creator_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX polls_creator_id_created_at_index ON public.polls USING btree (creator_id, created_at);


--
-- Name: polls_expires_at_is_closed_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX polls_expires_at_is_closed_index ON public.polls USING btree (expires_at, is_closed);


--
-- Name: polls_message_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX polls_message_id_created_at_index ON public.polls USING btree (message_id, created_at);


--
-- Name: quantum_key_encapsulation_algorithm_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quantum_key_encapsulation_algorithm_index ON public.quantum_key_encapsulation USING btree (algorithm);


--
-- Name: quantum_key_encapsulation_session_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quantum_key_encapsulation_session_id_is_active_index ON public.quantum_key_encapsulation USING btree (session_id, is_active);


--
-- Name: rate_limit_configs_is_active_action_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rate_limit_configs_is_active_action_name_index ON public.rate_limit_configs USING btree (is_active, action_name);


--
-- Name: rate_limits_key_action_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rate_limits_key_action_index ON public.rate_limits USING btree (key, action);


--
-- Name: rate_limits_reset_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rate_limits_reset_at_index ON public.rate_limits USING btree (reset_at);


--
-- Name: rate_limits_window_end_is_blocked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rate_limits_window_end_is_blocked_index ON public.rate_limits USING btree (window_end, is_blocked);


--
-- Name: ref_geo_city_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_city_created_by_index ON public.ref_geo_city USING btree (created_by);


--
-- Name: ref_geo_city_province_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_city_province_id_index ON public.ref_geo_city USING btree (province_id);


--
-- Name: ref_geo_city_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_city_updated_by_index ON public.ref_geo_city USING btree (updated_by);


--
-- Name: ref_geo_country_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_country_created_by_index ON public.ref_geo_country USING btree (created_by);


--
-- Name: ref_geo_country_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_country_updated_by_index ON public.ref_geo_country USING btree (updated_by);


--
-- Name: ref_geo_district_city_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_district_city_id_index ON public.ref_geo_district USING btree (city_id);


--
-- Name: ref_geo_district_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_district_created_by_index ON public.ref_geo_district USING btree (created_by);


--
-- Name: ref_geo_district_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_district_updated_by_index ON public.ref_geo_district USING btree (updated_by);


--
-- Name: ref_geo_province_country_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_province_country_id_index ON public.ref_geo_province USING btree (country_id);


--
-- Name: ref_geo_province_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_province_created_by_index ON public.ref_geo_province USING btree (created_by);


--
-- Name: ref_geo_province_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_province_updated_by_index ON public.ref_geo_province USING btree (updated_by);


--
-- Name: ref_geo_village_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_village_created_by_index ON public.ref_geo_village USING btree (created_by);


--
-- Name: ref_geo_village_district_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_village_district_id_index ON public.ref_geo_village USING btree (district_id);


--
-- Name: ref_geo_village_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ref_geo_village_updated_by_index ON public.ref_geo_village USING btree (updated_by);


--
-- Name: roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX roles_team_foreign_key_index ON public.sys_roles USING btree (team_id);


--
-- Name: scheduled_messages_conversation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scheduled_messages_conversation_id_status_index ON public.scheduled_messages USING btree (conversation_id, status);


--
-- Name: scheduled_messages_scheduled_for_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scheduled_messages_scheduled_for_index ON public.scheduled_messages USING btree (scheduled_for);


--
-- Name: scheduled_messages_sender_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scheduled_messages_sender_id_status_index ON public.scheduled_messages USING btree (sender_id, status);


--
-- Name: scheduled_messages_status_retry_count_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scheduled_messages_status_retry_count_index ON public.scheduled_messages USING btree (status, retry_count);


--
-- Name: scheduled_messages_status_scheduled_for_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scheduled_messages_status_scheduled_for_index ON public.scheduled_messages USING btree (status, scheduled_for);


--
-- Name: security_audit_logs_event_type_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_event_type_created_at_index ON public.security_audit_logs USING btree (event_type, created_at);


--
-- Name: security_audit_logs_organization_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_organization_id_created_at_index ON public.security_audit_logs USING btree (organization_id, created_at);


--
-- Name: security_audit_logs_risk_score_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_risk_score_status_index ON public.security_audit_logs USING btree (risk_score, status);


--
-- Name: security_audit_logs_severity_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_severity_created_at_index ON public.security_audit_logs USING btree (severity, created_at);


--
-- Name: security_audit_logs_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_status_index ON public.security_audit_logs USING btree (status);


--
-- Name: security_audit_logs_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX security_audit_logs_user_id_created_at_index ON public.security_audit_logs USING btree (user_id, created_at);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: signal_identity_keys_key_fingerprint_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_identity_keys_key_fingerprint_index ON public.signal_identity_keys USING btree (key_fingerprint);


--
-- Name: signal_one_time_prekeys_expires_at_used_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_one_time_prekeys_expires_at_used_at_index ON public.signal_one_time_prekeys USING btree (expires_at, used_at);


--
-- Name: signal_one_time_prekeys_user_id_device_id_used_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_one_time_prekeys_user_id_device_id_used_at_index ON public.signal_one_time_prekeys USING btree (user_id, device_id, used_at);


--
-- Name: signal_sessions_last_used_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_sessions_last_used_at_index ON public.signal_sessions USING btree (last_used_at);


--
-- Name: signal_sessions_local_user_id_local_device_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_sessions_local_user_id_local_device_id_is_active_index ON public.signal_sessions USING btree (local_user_id, local_device_id, is_active);


--
-- Name: signal_signed_prekeys_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_signed_prekeys_expires_at_index ON public.signal_signed_prekeys USING btree (expires_at);


--
-- Name: signal_signed_prekeys_user_id_device_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signal_signed_prekeys_user_id_device_id_is_active_index ON public.signal_signed_prekeys USING btree (user_id, device_id, is_active);


--
-- Name: snapshots_aggregate_ulid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX snapshots_aggregate_ulid_index ON public.snapshots USING btree (aggregate_ulid);


--
-- Name: spam_detections_confidence_score_action_taken_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spam_detections_confidence_score_action_taken_index ON public.spam_detections USING btree (confidence_score, action_taken);


--
-- Name: spam_detections_conversation_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spam_detections_conversation_id_created_at_index ON public.spam_detections USING btree (conversation_id, created_at);


--
-- Name: spam_detections_is_false_positive_reviewed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spam_detections_is_false_positive_reviewed_at_index ON public.spam_detections USING btree (is_false_positive, reviewed_at);


--
-- Name: spam_detections_user_id_detection_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX spam_detections_user_id_detection_type_index ON public.spam_detections USING btree (user_id, detection_type);


--
-- Name: stored_events_aggregate_ulid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stored_events_aggregate_ulid_index ON public.stored_events USING btree (aggregate_ulid);


--
-- Name: stored_events_event_class_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stored_events_event_class_index ON public.stored_events USING btree (event_class);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: survey_question_responses_question_id_answered_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_question_responses_question_id_answered_at_index ON public.survey_question_responses USING btree (question_id, answered_at);


--
-- Name: survey_questions_survey_id_question_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_questions_survey_id_question_order_index ON public.survey_questions USING btree (survey_id, question_order);


--
-- Name: survey_responses_respondent_id_started_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_responses_respondent_id_started_at_index ON public.survey_responses USING btree (respondent_id, started_at);


--
-- Name: survey_responses_survey_id_completed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX survey_responses_survey_id_completed_at_index ON public.survey_responses USING btree (survey_id, completed_at);


--
-- Name: surveys_creator_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX surveys_creator_id_created_at_index ON public.surveys USING btree (creator_id, created_at);


--
-- Name: surveys_expires_at_is_closed_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX surveys_expires_at_is_closed_index ON public.surveys USING btree (expires_at, is_closed);


--
-- Name: surveys_message_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX surveys_message_id_created_at_index ON public.surveys USING btree (message_id, created_at);


--
-- Name: suspicious_activities_detection_method_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX suspicious_activities_detection_method_created_at_index ON public.suspicious_activities USING btree (detection_method, created_at);


--
-- Name: suspicious_activities_severity_score_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX suspicious_activities_severity_score_status_index ON public.suspicious_activities USING btree (severity_score, status);


--
-- Name: suspicious_activities_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX suspicious_activities_status_created_at_index ON public.suspicious_activities USING btree (status, created_at);


--
-- Name: suspicious_activities_user_id_activity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX suspicious_activities_user_id_activity_type_index ON public.suspicious_activities USING btree (user_id, activity_type);


--
-- Name: sys_permissions_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_permissions_created_by_index ON public.sys_permissions USING btree (created_by);


--
-- Name: sys_permissions_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_permissions_updated_by_index ON public.sys_permissions USING btree (updated_by);


--
-- Name: sys_roles_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_roles_created_by_index ON public.sys_roles USING btree (created_by);


--
-- Name: sys_roles_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_roles_updated_by_index ON public.sys_roles USING btree (updated_by);


--
-- Name: sys_users_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_users_created_by_index ON public.sys_users USING btree (created_by);


--
-- Name: sys_users_updated_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_users_updated_by_index ON public.sys_users USING btree (updated_by);


--
-- Name: user_devices_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_devices_user_id_is_active_index ON public.user_devices USING btree (user_id, is_active);


--
-- Name: user_devices_user_id_is_trusted_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_devices_user_id_is_trusted_index ON public.user_devices USING btree (user_id, is_trusted);


--
-- Name: user_penalties_expires_at_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_penalties_expires_at_is_active_index ON public.user_penalties USING btree (expires_at, is_active);


--
-- Name: user_penalties_penalty_type_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_penalties_penalty_type_is_active_index ON public.user_penalties USING btree (penalty_type, is_active);


--
-- Name: user_penalties_severity_level_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_penalties_severity_level_created_at_index ON public.user_penalties USING btree (severity_level, created_at);


--
-- Name: user_penalties_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_penalties_user_id_is_active_index ON public.user_penalties USING btree (user_id, is_active);


--
-- Name: video_call_e2ee_logs_key_operation_operation_success_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_e2ee_logs_key_operation_operation_success_index ON public.video_call_e2ee_logs USING btree (key_operation, operation_success);


--
-- Name: video_call_e2ee_logs_video_call_id_operation_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_e2ee_logs_video_call_id_operation_timestamp_index ON public.video_call_e2ee_logs USING btree (video_call_id, operation_timestamp);


--
-- Name: video_call_events_event_type_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_events_event_type_created_at_index ON public.video_call_events USING btree (event_type, created_at);


--
-- Name: video_call_events_video_call_id_event_timestamp_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_events_video_call_id_event_timestamp_index ON public.video_call_events USING btree (video_call_id, event_timestamp);


--
-- Name: video_call_participants_participant_identity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_participants_participant_identity_index ON public.video_call_participants USING btree (participant_identity);


--
-- Name: video_call_participants_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_participants_user_id_status_index ON public.video_call_participants USING btree (user_id, status);


--
-- Name: video_call_quality_metrics_participant_id_measured_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_quality_metrics_participant_id_measured_at_index ON public.video_call_quality_metrics USING btree (participant_id, measured_at);


--
-- Name: video_call_quality_metrics_video_call_id_measured_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_quality_metrics_video_call_id_measured_at_index ON public.video_call_quality_metrics USING btree (video_call_id, measured_at);


--
-- Name: video_call_recordings_processing_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_recordings_processing_status_index ON public.video_call_recordings USING btree (processing_status);


--
-- Name: video_call_recordings_recording_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_recordings_recording_id_index ON public.video_call_recordings USING btree (recording_id);


--
-- Name: video_call_recordings_video_call_id_processing_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_call_recordings_video_call_id_processing_status_index ON public.video_call_recordings USING btree (video_call_id, processing_status);


--
-- Name: video_calls_conversation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_calls_conversation_id_status_index ON public.video_calls USING btree (conversation_id, status);


--
-- Name: video_calls_initiated_by_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_calls_initiated_by_created_at_index ON public.video_calls USING btree (initiated_by, created_at);


--
-- Name: video_calls_livekit_room_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_calls_livekit_room_name_index ON public.video_calls USING btree (livekit_room_name);


--
-- Name: video_calls_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX video_calls_status_index ON public.video_calls USING btree (status);


--
-- Name: voice_transcriptions_language_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX voice_transcriptions_language_index ON public.voice_transcriptions USING btree (language);


--
-- Name: voice_transcriptions_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX voice_transcriptions_message_id_index ON public.voice_transcriptions USING btree (message_id);


--
-- Name: voice_transcriptions_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX voice_transcriptions_provider_index ON public.voice_transcriptions USING btree (provider);


--
-- Name: voice_transcriptions_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX voice_transcriptions_status_created_at_index ON public.voice_transcriptions USING btree (status, created_at);


--
-- Name: voice_transcriptions_transcript_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX voice_transcriptions_transcript_fulltext ON public.voice_transcriptions USING gin (to_tsvector('english'::regconfig, transcript));


--
-- Name: webhook_deliveries_event_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_deliveries_event_type_index ON public.webhook_deliveries USING btree (event_type);


--
-- Name: webhook_deliveries_status_next_retry_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_deliveries_status_next_retry_at_index ON public.webhook_deliveries USING btree (status, next_retry_at);


--
-- Name: webhook_deliveries_webhook_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_deliveries_webhook_id_status_index ON public.webhook_deliveries USING btree (webhook_id, status);


--
-- Name: webhooks_organization_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhooks_organization_id_status_index ON public.webhooks USING btree (organization_id, status);


--
-- Name: webhooks_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhooks_status_index ON public.webhooks USING btree (status);


--
-- Name: abuse_reports abuse_reports_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: abuse_reports abuse_reports_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: abuse_reports abuse_reports_reported_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_reported_user_id_foreign FOREIGN KEY (reported_user_id) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: abuse_reports abuse_reports_reporter_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_reporter_user_id_foreign FOREIGN KEY (reporter_user_id) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: abuse_reports abuse_reports_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.abuse_reports
    ADD CONSTRAINT abuse_reports_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: activity_log activity_log_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: backup_restorations backup_restorations_backup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.backup_restorations
    ADD CONSTRAINT backup_restorations_backup_id_foreign FOREIGN KEY (backup_id) REFERENCES public.chat_backups(id) ON DELETE SET NULL;


--
-- Name: backup_restorations backup_restorations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.backup_restorations
    ADD CONSTRAINT backup_restorations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: backup_verification backup_verification_backup_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.backup_verification
    ADD CONSTRAINT backup_verification_backup_id_foreign FOREIGN KEY (backup_id) REFERENCES public.chat_backups(id) ON DELETE CASCADE;


--
-- Name: bot_conversations bot_conversations_bot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_conversations
    ADD CONSTRAINT bot_conversations_bot_id_foreign FOREIGN KEY (bot_id) REFERENCES public.bots(id) ON DELETE CASCADE;


--
-- Name: bot_conversations bot_conversations_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_conversations
    ADD CONSTRAINT bot_conversations_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: bot_encryption_keys bot_encryption_keys_bot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_encryption_keys
    ADD CONSTRAINT bot_encryption_keys_bot_id_foreign FOREIGN KEY (bot_id) REFERENCES public.bots(id) ON DELETE CASCADE;


--
-- Name: bot_encryption_keys bot_encryption_keys_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_encryption_keys
    ADD CONSTRAINT bot_encryption_keys_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: bot_messages bot_messages_bot_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_messages
    ADD CONSTRAINT bot_messages_bot_conversation_id_foreign FOREIGN KEY (bot_conversation_id) REFERENCES public.bot_conversations(id) ON DELETE CASCADE;


--
-- Name: bot_messages bot_messages_bot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_messages
    ADD CONSTRAINT bot_messages_bot_id_foreign FOREIGN KEY (bot_id) REFERENCES public.bots(id) ON DELETE CASCADE;


--
-- Name: bot_messages bot_messages_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_messages
    ADD CONSTRAINT bot_messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: bot_messages bot_messages_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_messages
    ADD CONSTRAINT bot_messages_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: bots bots_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bots
    ADD CONSTRAINT bots_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: bots bots_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bots
    ADD CONSTRAINT bots_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: chat_backups chat_backups_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_backups
    ADD CONSTRAINT chat_backups_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: chat_backups chat_backups_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_backups
    ADD CONSTRAINT chat_backups_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_conversations chat_conversations_created_by_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_conversations
    ADD CONSTRAINT chat_conversations_created_by_device_id_foreign FOREIGN KEY (created_by_device_id) REFERENCES public.user_devices(id) ON DELETE SET NULL;


--
-- Name: chat_conversations chat_conversations_created_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_conversations
    ADD CONSTRAINT chat_conversations_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_conversations chat_conversations_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_conversations
    ADD CONSTRAINT chat_conversations_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: chat_encryption_keys chat_encryption_keys_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: chat_encryption_keys chat_encryption_keys_created_by_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_created_by_device_id_foreign FOREIGN KEY (created_by_device_id) REFERENCES public.user_devices(id) ON DELETE SET NULL;


--
-- Name: chat_encryption_keys chat_encryption_keys_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: chat_encryption_keys chat_encryption_keys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_encryption_keys
    ADD CONSTRAINT chat_encryption_keys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_messages chat_messages_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: chat_messages chat_messages_reply_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_reply_to_id_foreign FOREIGN KEY (reply_to_id) REFERENCES public.chat_messages(id) ON DELETE SET NULL;


--
-- Name: chat_messages chat_messages_sender_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_sender_device_id_foreign FOREIGN KEY (sender_device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: chat_messages chat_messages_sender_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_messages chat_messages_thread_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_messages
    ADD CONSTRAINT chat_messages_thread_id_foreign FOREIGN KEY (thread_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: chat_thread_participants chat_thread_participants_last_read_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_thread_participants
    ADD CONSTRAINT chat_thread_participants_last_read_message_id_foreign FOREIGN KEY (last_read_message_id) REFERENCES public.chat_messages(id) ON DELETE SET NULL;


--
-- Name: chat_thread_participants chat_thread_participants_thread_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_thread_participants
    ADD CONSTRAINT chat_thread_participants_thread_id_foreign FOREIGN KEY (thread_id) REFERENCES public.chat_threads(id) ON DELETE CASCADE;


--
-- Name: chat_thread_participants chat_thread_participants_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_thread_participants
    ADD CONSTRAINT chat_thread_participants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_threads chat_threads_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_threads
    ADD CONSTRAINT chat_threads_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: chat_threads chat_threads_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_threads
    ADD CONSTRAINT chat_threads_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: chat_threads chat_threads_last_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_threads
    ADD CONSTRAINT chat_threads_last_message_id_foreign FOREIGN KEY (last_message_id) REFERENCES public.chat_messages(id) ON DELETE SET NULL;


--
-- Name: chat_threads chat_threads_parent_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_threads
    ADD CONSTRAINT chat_threads_parent_message_id_foreign FOREIGN KEY (parent_message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: conversation_key_bundles conversation_key_bundles_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_key_bundles
    ADD CONSTRAINT conversation_key_bundles_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: conversation_key_bundles conversation_key_bundles_participant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_key_bundles
    ADD CONSTRAINT conversation_key_bundles_participant_id_foreign FOREIGN KEY (participant_id) REFERENCES public.conversation_participants(id) ON DELETE CASCADE;


--
-- Name: conversation_participants conversation_participants_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: conversation_participants conversation_participants_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE SET NULL;


--
-- Name: conversation_participants conversation_participants_last_read_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_last_read_message_id_foreign FOREIGN KEY (last_read_message_id) REFERENCES public.chat_messages(id) ON DELETE SET NULL;


--
-- Name: conversation_participants conversation_participants_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_participants
    ADD CONSTRAINT conversation_participants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: export_templates export_templates_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.export_templates
    ADD CONSTRAINT export_templates_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: ip_restrictions ip_restrictions_applied_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ip_restrictions
    ADD CONSTRAINT ip_restrictions_applied_by_foreign FOREIGN KEY (applied_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: message_attachments message_attachments_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_delivery_receipts message_delivery_receipts_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_delivery_receipts
    ADD CONSTRAINT message_delivery_receipts_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_delivery_receipts message_delivery_receipts_recipient_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_delivery_receipts
    ADD CONSTRAINT message_delivery_receipts_recipient_device_id_foreign FOREIGN KEY (recipient_device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: message_delivery_receipts message_delivery_receipts_recipient_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_delivery_receipts
    ADD CONSTRAINT message_delivery_receipts_recipient_user_id_foreign FOREIGN KEY (recipient_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: message_files message_files_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_files
    ADD CONSTRAINT message_files_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_mentions message_mentions_mentioned_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_mentions
    ADD CONSTRAINT message_mentions_mentioned_user_id_foreign FOREIGN KEY (mentioned_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: message_mentions message_mentions_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_mentions
    ADD CONSTRAINT message_mentions_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_poll_votes message_poll_votes_poll_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_poll_votes
    ADD CONSTRAINT message_poll_votes_poll_id_foreign FOREIGN KEY (poll_id) REFERENCES public.message_polls(id) ON DELETE CASCADE;


--
-- Name: message_poll_votes message_poll_votes_voter_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_poll_votes
    ADD CONSTRAINT message_poll_votes_voter_device_id_foreign FOREIGN KEY (voter_device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: message_poll_votes message_poll_votes_voter_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_poll_votes
    ADD CONSTRAINT message_poll_votes_voter_user_id_foreign FOREIGN KEY (voter_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: message_polls message_polls_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_polls
    ADD CONSTRAINT message_polls_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_reactions message_reactions_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: message_reactions message_reactions_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: message_reactions message_reactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_reactions
    ADD CONSTRAINT message_reactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: mobile_pass_registrations mobile_pass_registrations_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_pass_registrations
    ADD CONSTRAINT mobile_pass_registrations_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.mobile_pass_devices(id);


--
-- Name: mobile_pass_registrations mobile_pass_registrations_pass_serial_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_pass_registrations
    ADD CONSTRAINT mobile_pass_registrations_pass_serial_foreign FOREIGN KEY (pass_serial) REFERENCES public.mobile_passes(id);


--
-- Name: oauth_access_tokens oauth_access_tokens_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_access_tokens
    ADD CONSTRAINT oauth_access_tokens_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: oauth_access_tokens oauth_access_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_access_tokens
    ADD CONSTRAINT oauth_access_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: oauth_audit_logs oauth_audit_logs_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_audit_logs
    ADD CONSTRAINT oauth_audit_logs_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: oauth_audit_logs oauth_audit_logs_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_audit_logs
    ADD CONSTRAINT oauth_audit_logs_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: oauth_audit_logs oauth_audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_audit_logs
    ADD CONSTRAINT oauth_audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: oauth_auth_codes oauth_auth_codes_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_auth_codes
    ADD CONSTRAINT oauth_auth_codes_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: oauth_auth_codes oauth_auth_codes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_auth_codes
    ADD CONSTRAINT oauth_auth_codes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: oauth_clients oauth_clients_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_clients
    ADD CONSTRAINT oauth_clients_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: oauth_refresh_tokens oauth_refresh_tokens_access_token_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.oauth_refresh_tokens
    ADD CONSTRAINT oauth_refresh_tokens_access_token_id_foreign FOREIGN KEY (access_token_id) REFERENCES public.oauth_access_tokens(id) ON DELETE CASCADE;


--
-- Name: organization_memberships organization_memberships_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: organization_memberships organization_memberships_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id);


--
-- Name: organization_memberships organization_memberships_organization_position_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_position_id_foreign FOREIGN KEY (organization_position_id) REFERENCES public.organization_positions(id);


--
-- Name: organization_memberships organization_memberships_organization_unit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_organization_unit_id_foreign FOREIGN KEY (organization_unit_id) REFERENCES public.organization_units(id);


--
-- Name: organization_memberships organization_memberships_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: organization_memberships organization_memberships_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_memberships
    ADD CONSTRAINT organization_memberships_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id);


--
-- Name: organization_positions organization_positions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: organization_positions organization_positions_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_positions organization_positions_organization_position_level_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_organization_position_level_id_foreign FOREIGN KEY (organization_position_level_id) REFERENCES public.organization_position_levels(id);


--
-- Name: organization_positions organization_positions_organization_unit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_organization_unit_id_foreign FOREIGN KEY (organization_unit_id) REFERENCES public.organization_units(id) ON DELETE CASCADE;


--
-- Name: organization_positions organization_positions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_positions
    ADD CONSTRAINT organization_positions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: organization_units organization_units_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: organization_units organization_units_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_units organization_units_parent_unit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_parent_unit_id_foreign FOREIGN KEY (parent_unit_id) REFERENCES public.organization_units(id) ON DELETE CASCADE;


--
-- Name: organization_units organization_units_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organization_units
    ADD CONSTRAINT organization_units_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: organizations organizations_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: organizations organizations_parent_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_parent_organization_id_foreign FOREIGN KEY (parent_organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organizations organizations_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: passkeys passkeys_authenticatable_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_authenticatable_fk FOREIGN KEY (authenticatable_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: poll_analytics poll_analytics_poll_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_analytics
    ADD CONSTRAINT poll_analytics_poll_id_foreign FOREIGN KEY (poll_id) REFERENCES public.polls(id) ON DELETE CASCADE;


--
-- Name: poll_options poll_options_poll_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_options
    ADD CONSTRAINT poll_options_poll_id_foreign FOREIGN KEY (poll_id) REFERENCES public.polls(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_poll_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_poll_id_foreign FOREIGN KEY (poll_id) REFERENCES public.polls(id) ON DELETE CASCADE;


--
-- Name: poll_votes poll_votes_voter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.poll_votes
    ADD CONSTRAINT poll_votes_voter_id_foreign FOREIGN KEY (voter_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: polls polls_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polls
    ADD CONSTRAINT polls_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: polls polls_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.polls
    ADD CONSTRAINT polls_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: quantum_key_encapsulation quantum_key_encapsulation_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quantum_key_encapsulation
    ADD CONSTRAINT quantum_key_encapsulation_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.signal_sessions(id) ON DELETE CASCADE;


--
-- Name: ref_geo_city ref_geo_city_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_city
    ADD CONSTRAINT ref_geo_city_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_city ref_geo_city_province_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_city
    ADD CONSTRAINT ref_geo_city_province_id_foreign FOREIGN KEY (province_id) REFERENCES public.ref_geo_province(id);


--
-- Name: ref_geo_city ref_geo_city_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_city
    ADD CONSTRAINT ref_geo_city_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_country ref_geo_country_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_country
    ADD CONSTRAINT ref_geo_country_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_country ref_geo_country_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_country
    ADD CONSTRAINT ref_geo_country_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_district ref_geo_district_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_district
    ADD CONSTRAINT ref_geo_district_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.ref_geo_city(id);


--
-- Name: ref_geo_district ref_geo_district_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_district
    ADD CONSTRAINT ref_geo_district_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_district ref_geo_district_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_district
    ADD CONSTRAINT ref_geo_district_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_province ref_geo_province_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_province
    ADD CONSTRAINT ref_geo_province_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.ref_geo_country(id);


--
-- Name: ref_geo_province ref_geo_province_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_province
    ADD CONSTRAINT ref_geo_province_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_province ref_geo_province_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_province
    ADD CONSTRAINT ref_geo_province_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_village ref_geo_village_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_village
    ADD CONSTRAINT ref_geo_village_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: ref_geo_village ref_geo_village_district_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_village
    ADD CONSTRAINT ref_geo_village_district_id_foreign FOREIGN KEY (district_id) REFERENCES public.ref_geo_district(id);


--
-- Name: ref_geo_village ref_geo_village_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ref_geo_village
    ADD CONSTRAINT ref_geo_village_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: scheduled_messages scheduled_messages_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scheduled_messages
    ADD CONSTRAINT scheduled_messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: scheduled_messages scheduled_messages_sender_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scheduled_messages
    ADD CONSTRAINT scheduled_messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: scheduled_messages scheduled_messages_sent_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scheduled_messages
    ADD CONSTRAINT scheduled_messages_sent_message_id_foreign FOREIGN KEY (sent_message_id) REFERENCES public.chat_messages(id) ON DELETE SET NULL;


--
-- Name: security_audit_logs security_audit_logs_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE SET NULL;


--
-- Name: security_audit_logs security_audit_logs_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE SET NULL;


--
-- Name: security_audit_logs security_audit_logs_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: security_audit_logs security_audit_logs_resolved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: security_audit_logs security_audit_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.security_audit_logs
    ADD CONSTRAINT security_audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: sessions sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id);


--
-- Name: signal_identity_keys signal_identity_keys_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_identity_keys
    ADD CONSTRAINT signal_identity_keys_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: signal_identity_keys signal_identity_keys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_identity_keys
    ADD CONSTRAINT signal_identity_keys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: signal_one_time_prekeys signal_one_time_prekeys_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_one_time_prekeys
    ADD CONSTRAINT signal_one_time_prekeys_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: signal_one_time_prekeys signal_one_time_prekeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_one_time_prekeys
    ADD CONSTRAINT signal_one_time_prekeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: signal_sessions signal_sessions_local_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_local_device_id_foreign FOREIGN KEY (local_device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: signal_sessions signal_sessions_local_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_local_user_id_foreign FOREIGN KEY (local_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: signal_sessions signal_sessions_remote_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_remote_device_id_foreign FOREIGN KEY (remote_device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: signal_sessions signal_sessions_remote_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_sessions
    ADD CONSTRAINT signal_sessions_remote_user_id_foreign FOREIGN KEY (remote_user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: signal_signed_prekeys signal_signed_prekeys_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_signed_prekeys
    ADD CONSTRAINT signal_signed_prekeys_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: signal_signed_prekeys signal_signed_prekeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signal_signed_prekeys
    ADD CONSTRAINT signal_signed_prekeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: spam_detections spam_detections_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_detections
    ADD CONSTRAINT spam_detections_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: spam_detections spam_detections_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_detections
    ADD CONSTRAINT spam_detections_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: spam_detections spam_detections_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_detections
    ADD CONSTRAINT spam_detections_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: spam_detections spam_detections_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_detections
    ADD CONSTRAINT spam_detections_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: survey_question_responses survey_question_responses_question_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_question_responses
    ADD CONSTRAINT survey_question_responses_question_id_foreign FOREIGN KEY (question_id) REFERENCES public.survey_questions(id) ON DELETE CASCADE;


--
-- Name: survey_question_responses survey_question_responses_survey_response_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_question_responses
    ADD CONSTRAINT survey_question_responses_survey_response_id_foreign FOREIGN KEY (survey_response_id) REFERENCES public.survey_responses(id) ON DELETE CASCADE;


--
-- Name: survey_questions survey_questions_survey_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_questions
    ADD CONSTRAINT survey_questions_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES public.surveys(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_device_id_foreign FOREIGN KEY (device_id) REFERENCES public.user_devices(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_respondent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_respondent_id_foreign FOREIGN KEY (respondent_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: survey_responses survey_responses_survey_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.survey_responses
    ADD CONSTRAINT survey_responses_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES public.surveys(id) ON DELETE CASCADE;


--
-- Name: surveys surveys_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: surveys surveys_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.surveys
    ADD CONSTRAINT surveys_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: suspicious_activities suspicious_activities_investigated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suspicious_activities
    ADD CONSTRAINT suspicious_activities_investigated_by_foreign FOREIGN KEY (investigated_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: suspicious_activities suspicious_activities_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suspicious_activities
    ADD CONSTRAINT suspicious_activities_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: sys_model_has_permissions sys_model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_model_has_permissions
    ADD CONSTRAINT sys_model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.sys_permissions(id) ON DELETE CASCADE;


--
-- Name: sys_model_has_roles sys_model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_model_has_roles
    ADD CONSTRAINT sys_model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.sys_roles(id) ON DELETE CASCADE;


--
-- Name: sys_permissions sys_permissions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_permissions
    ADD CONSTRAINT sys_permissions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: sys_permissions sys_permissions_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_permissions
    ADD CONSTRAINT sys_permissions_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: sys_role_has_permissions sys_role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_role_has_permissions
    ADD CONSTRAINT sys_role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.sys_permissions(id) ON DELETE CASCADE;


--
-- Name: sys_role_has_permissions sys_role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_role_has_permissions
    ADD CONSTRAINT sys_role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.sys_roles(id) ON DELETE CASCADE;


--
-- Name: sys_roles sys_roles_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_roles
    ADD CONSTRAINT sys_roles_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: sys_roles sys_roles_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_roles
    ADD CONSTRAINT sys_roles_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: sys_users sys_users_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id);


--
-- Name: sys_users sys_users_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_users
    ADD CONSTRAINT sys_users_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.sys_users(id);


--
-- Name: user_consents user_consents_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_consents
    ADD CONSTRAINT user_consents_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.oauth_clients(id) ON DELETE CASCADE;


--
-- Name: user_consents user_consents_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_consents
    ADD CONSTRAINT user_consents_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: user_devices user_devices_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_devices
    ADD CONSTRAINT user_devices_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: user_mfa_settings user_mfa_settings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_mfa_settings
    ADD CONSTRAINT user_mfa_settings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: user_penalties user_penalties_applied_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_penalties
    ADD CONSTRAINT user_penalties_applied_by_foreign FOREIGN KEY (applied_by) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: user_penalties user_penalties_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_penalties
    ADD CONSTRAINT user_penalties_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: video_call_e2ee_logs video_call_e2ee_logs_participant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_e2ee_logs
    ADD CONSTRAINT video_call_e2ee_logs_participant_id_foreign FOREIGN KEY (participant_id) REFERENCES public.video_call_participants(id) ON DELETE CASCADE;


--
-- Name: video_call_e2ee_logs video_call_e2ee_logs_video_call_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_e2ee_logs
    ADD CONSTRAINT video_call_e2ee_logs_video_call_id_foreign FOREIGN KEY (video_call_id) REFERENCES public.video_calls(id) ON DELETE CASCADE;


--
-- Name: video_call_events video_call_events_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_events
    ADD CONSTRAINT video_call_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE SET NULL;


--
-- Name: video_call_events video_call_events_video_call_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_events
    ADD CONSTRAINT video_call_events_video_call_id_foreign FOREIGN KEY (video_call_id) REFERENCES public.video_calls(id) ON DELETE CASCADE;


--
-- Name: video_call_participants video_call_participants_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_participants
    ADD CONSTRAINT video_call_participants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: video_call_participants video_call_participants_video_call_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_participants
    ADD CONSTRAINT video_call_participants_video_call_id_foreign FOREIGN KEY (video_call_id) REFERENCES public.video_calls(id) ON DELETE CASCADE;


--
-- Name: video_call_quality_metrics video_call_quality_metrics_participant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_quality_metrics
    ADD CONSTRAINT video_call_quality_metrics_participant_id_foreign FOREIGN KEY (participant_id) REFERENCES public.video_call_participants(id) ON DELETE CASCADE;


--
-- Name: video_call_quality_metrics video_call_quality_metrics_video_call_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_quality_metrics
    ADD CONSTRAINT video_call_quality_metrics_video_call_id_foreign FOREIGN KEY (video_call_id) REFERENCES public.video_calls(id) ON DELETE CASCADE;


--
-- Name: video_call_recordings video_call_recordings_video_call_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_call_recordings
    ADD CONSTRAINT video_call_recordings_video_call_id_foreign FOREIGN KEY (video_call_id) REFERENCES public.video_calls(id) ON DELETE CASCADE;


--
-- Name: video_calls video_calls_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_calls
    ADD CONSTRAINT video_calls_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.chat_conversations(id) ON DELETE CASCADE;


--
-- Name: video_calls video_calls_initiated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.video_calls
    ADD CONSTRAINT video_calls_initiated_by_foreign FOREIGN KEY (initiated_by) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: voice_transcriptions voice_transcriptions_attachment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voice_transcriptions
    ADD CONSTRAINT voice_transcriptions_attachment_id_foreign FOREIGN KEY (attachment_id) REFERENCES public.message_files(id) ON DELETE CASCADE;


--
-- Name: voice_transcriptions voice_transcriptions_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voice_transcriptions
    ADD CONSTRAINT voice_transcriptions_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.chat_messages(id) ON DELETE CASCADE;


--
-- Name: webhook_deliveries webhook_deliveries_webhook_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_webhook_id_foreign FOREIGN KEY (webhook_id) REFERENCES public.webhooks(id) ON DELETE CASCADE;


--
-- Name: webhooks webhooks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.sys_users(id) ON DELETE CASCADE;


--
-- Name: webhooks webhooks_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhooks
    ADD CONSTRAINT webhooks_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict c1cpUBInhxN6AeLA5FEzolgT5n94dNgHfX9VRboUGCFRwiRum5qBM5tJahQQg0T

--
-- PostgreSQL database dump
--

\restrict 90p8tj1nqlQ7S0GVhSKtIDJtQ24pxqKc6TgDxjlfkMchHag1mxHTIM7Lc2fQ21r

-- Dumped from database version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)
-- Dumped by pg_dump version 17.6 (Ubuntu 17.6-1.pgdg24.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_08_22_133201_create_ref_geo	1
5	2025_08_22_134012_create_oauth_clients_table	1
6	2025_08_22_134013_create_oauth_access_tokens_table	1
7	2025_08_22_134014_create_oauth_refresh_tokens_table	1
8	2025_08_22_134017_create_oauth_auth_codes_table	1
9	2025_08_22_134759_create_permission_tables	1
10	2025_08_22_134800_create_mobile_pass_tables	1
11	2025_08_22_134800_create_notification_log_items_table	1
12	2025_08_22_134800_create_one_time_passwords_table	1
13	2025_08_22_134800_create_passkeys_table	1
14	2025_08_22_134929_create_stored_events_table	1
15	2025_08_22_134930_create_snapshots_table	1
16	2025_08_22_135002_create_activity_log_table	1
17	2025_08_22_135003_add_event_column_to_activity_log_table	1
18	2025_08_22_135004_add_batch_uuid_column_to_activity_log_table	1
19	2025_08_22_140206_create_organizations_table	1
20	2025_08_22_141831_create_user_mfa_settings_table	1
21	2025_08_22_144015_create_personal_access_tokens_table	1
22	2025_08_22_193811_create_user_consents_table	1
23	2025_08_22_193814_create_oauth_scopes_table	1
24	2025_08_22_193820_add_oauth_fields_to_oauth_clients_table	1
25	2025_08_22_194942_add_pkce_to_oauth_auth_codes_table	1
26	2025_08_22_195623_create_oauth_audit_logs_table	1
27	2025_08_22_200259_add_organization_support_to_oauth_clients_table	1
28	2025_08_22_200507_add_tenant_context_to_oauth_audit_logs_table	1
29	2025_08_22_201247_make_organization_id_required_on_oauth_clients_table	1
30	2025_08_23_000001_add_organization_context_to_activity_log	1
31	2025_08_23_085834_add_tenant_support_to_models	1
32	2025_08_23_174923_add_organization_id_to_organization_positions_table	1
33	2025_08_24_115507_add_avatar_to_users_table	1
34	2025_08_26_000000_add_user_access_scope_to_oauth_clients_table	1
35	2025_08_27_024052_migrate_to_new_naming_conventions	1
36	2025_08_27_081646_fix_oauth_clients_owner_id_for_ulid	1
37	2025_08_29_070201_add_manager_membership_type_to_organization_memberships	1
38	2025_08_29_230829_add_username_to_users_table	1
39	2025_08_29_232816_add_first_name_last_name_to_users_table	1
40	2025_08_29_233048_add_address_fields_to_users_table	1
41	2025_08_29_233402_add_missing_oidc_user_fields	1
42	2025_08_29_234015_enhance_user_consents_table	1
43	2025_09_05_013106_create_user_devices_table	1
44	2025_09_05_013111_create_signal_protocol_tables	1
45	2025_09_05_013115_create_conversations_table	1
46	2025_09_05_013120_create_messages_table	1
47	2025_09_05_013357_add_foreign_key_constraints_to_conversations	1
48	2025_09_05_052707_create_message_files_table	1
49	2025_09_05_053223_create_polls_and_surveys_tables	1
50	2025_09_05_054147_create_chat_backups_table	1
51	2025_09_05_054752_create_rate_limiting_tables	1
52	2025_09_05_060426_create_video_calls_table	1
53	2025_09_05_100410_create_webhooks_table	1
54	2025_09_05_100904_create_security_audit_logs_table	1
55	2025_09_05_create_chat_threading_tables	1
56	2025_09_06_113954_add_last_used_at_to_user_devices_table	1
57	2025_09_06_114119_add_deleted_at_to_chat_conversations_table	1
58	2025_09_06_115740_add_missing_columns_to_user_devices_table	1
59	2025_09_06_120209_add_encryption_version_to_user_devices_table	1
60	2025_09_06_120549_add_missing_columns_to_signal_identity_keys_table	1
61	2025_09_07_120000_create_bots_table	1
62	2025_09_07_120001_create_bot_conversations_table	1
63	2025_09_07_120002_create_bot_messages_table	1
64	2025_09_07_120003_create_bot_encryption_keys_table	1
65	2025_09_07_130000_create_voice_transcriptions_table	1
66	2025_09_07_140000_create_scheduled_messages_table	1
67	2025_09_06_231714_create_chat_encryption_keys_table	2
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 67, true);


--
-- PostgreSQL database dump complete
--

\unrestrict 90p8tj1nqlQ7S0GVhSKtIDJtQ24pxqKc6TgDxjlfkMchHag1mxHTIM7Lc2fQ21r

