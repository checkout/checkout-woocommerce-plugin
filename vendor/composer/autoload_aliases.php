<?php

// Functions and constants

namespace {
    if(!function_exists('\\getallheaders')){
        function getallheaders(...$args) {
            return \checkoutcomwc_vendor_getallheaders(...func_get_args());
        }
    }
    if(!function_exists('\\trigger_deprecation')){
        function trigger_deprecation(...$args) {
            return \checkoutcomwc_vendor_trigger_deprecation(...func_get_args());
        }
    }

}
namespace GuzzleHttp {
    if(!function_exists('\\GuzzleHttp\\describe_type')){
        function describe_type(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\describe_type(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\headers_from_lines')){
        function headers_from_lines(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\headers_from_lines(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\debug_resource')){
        function debug_resource(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\debug_resource(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\choose_handler')){
        function choose_handler(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\choose_handler(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\default_user_agent')){
        function default_user_agent(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\default_user_agent(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\default_ca_bundle')){
        function default_ca_bundle(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\default_ca_bundle(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\normalize_header_keys')){
        function normalize_header_keys(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\normalize_header_keys(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\is_host_in_noproxy')){
        function is_host_in_noproxy(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\is_host_in_noproxy(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\json_decode')){
        function json_decode(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\json_decode(...func_get_args());
        }
    }
    if(!function_exists('\\GuzzleHttp\\json_encode')){
        function json_encode(...$args) {
            return \CheckoutComWC\Vendor\GuzzleHttp\json_encode(...func_get_args());
        }
    }
}


namespace CheckoutComWC\Vendor {

    use BrianHenryIE\Strauss\Types\AutoloadAliasInterface;

    /**
     * @see AutoloadAliasInterface
     *
     * @phpstan-type ClassAliasArray array{'type':'class',isabstract:bool,classname:string,namespace?:string,extends:string,implements:array<string>}
     * @phpstan-type InterfaceAliasArray array{'type':'interface',interfacename:string,namespace?:string,extends:array<string>}
     * @phpstan-type TraitAliasArray array{'type':'trait',traitname:string,namespace?:string,use:array<string>}
     * @phpstan-type EnumAliasArray array{'type':'enum',enumname:string,namespace?:string,concrete:string}
     * @phpstan-type AutoloadAliasArray array<string,ClassAliasArray|InterfaceAliasArray|TraitAliasArray|EnumAliasArray>
     */
    class AliasAutoloader
    {
        private string $includeFilePath;

        /**
         * @var AutoloadAliasArray
         */
        private array $autoloadAliases = array (
  'GuzzleHttp\\BodySummarizer' => 
  array (
    'type' => 'class',
    'classname' => 'BodySummarizer',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\BodySummarizer',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\BodySummarizerInterface',
    ),
  ),
  'GuzzleHttp\\Client' => 
  array (
    'type' => 'class',
    'classname' => 'Client',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Client',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\ClientInterface',
      1 => 'Psr\\Http\\Client\\ClientInterface',
    ),
  ),
  'GuzzleHttp\\Cookie\\CookieJar' => 
  array (
    'type' => 'class',
    'classname' => 'CookieJar',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Cookie',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Cookie\\CookieJar',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Cookie\\CookieJarInterface',
    ),
  ),
  'GuzzleHttp\\Cookie\\FileCookieJar' => 
  array (
    'type' => 'class',
    'classname' => 'FileCookieJar',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Cookie',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Cookie\\FileCookieJar',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Cookie\\SessionCookieJar' => 
  array (
    'type' => 'class',
    'classname' => 'SessionCookieJar',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Cookie',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Cookie\\SessionCookieJar',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Cookie\\SetCookie' => 
  array (
    'type' => 'class',
    'classname' => 'SetCookie',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Cookie',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Cookie\\SetCookie',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Exception\\BadResponseException' => 
  array (
    'type' => 'class',
    'classname' => 'BadResponseException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\BadResponseException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Exception\\ClientException' => 
  array (
    'type' => 'class',
    'classname' => 'ClientException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\ClientException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Exception\\ConnectException' => 
  array (
    'type' => 'class',
    'classname' => 'ConnectException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\ConnectException',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Client\\NetworkExceptionInterface',
    ),
  ),
  'GuzzleHttp\\Exception\\InvalidArgumentException' => 
  array (
    'type' => 'class',
    'classname' => 'InvalidArgumentException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\InvalidArgumentException',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Exception\\GuzzleException',
    ),
  ),
  'GuzzleHttp\\Exception\\RequestException' => 
  array (
    'type' => 'class',
    'classname' => 'RequestException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\RequestException',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Client\\RequestExceptionInterface',
    ),
  ),
  'GuzzleHttp\\Exception\\ServerException' => 
  array (
    'type' => 'class',
    'classname' => 'ServerException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\ServerException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Exception\\TooManyRedirectsException' => 
  array (
    'type' => 'class',
    'classname' => 'TooManyRedirectsException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\TooManyRedirectsException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Exception\\TransferException' => 
  array (
    'type' => 'class',
    'classname' => 'TransferException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\TransferException',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Exception\\GuzzleException',
    ),
  ),
  'GuzzleHttp\\Handler\\CurlFactory' => 
  array (
    'type' => 'class',
    'classname' => 'CurlFactory',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlFactory',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Handler\\CurlFactoryInterface',
    ),
  ),
  'GuzzleHttp\\Handler\\CurlHandler' => 
  array (
    'type' => 'class',
    'classname' => 'CurlHandler',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlHandler',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\CurlMultiHandler' => 
  array (
    'type' => 'class',
    'classname' => 'CurlMultiHandler',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlMultiHandler',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\CurlShareHandleState' => 
  array (
    'type' => 'class',
    'classname' => 'CurlShareHandleState',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlShareHandleState',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\CurlVersion' => 
  array (
    'type' => 'class',
    'classname' => 'CurlVersion',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlVersion',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\EasyHandle' => 
  array (
    'type' => 'class',
    'classname' => 'EasyHandle',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\EasyHandle',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\HeaderProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'HeaderProcessor',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\HeaderProcessor',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\HostValidator' => 
  array (
    'type' => 'class',
    'classname' => 'HostValidator',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\HostValidator',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\MockHandler' => 
  array (
    'type' => 'class',
    'classname' => 'MockHandler',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\MockHandler',
    'implements' => 
    array (
      0 => 'Countable',
    ),
  ),
  'GuzzleHttp\\Handler\\Proxy' => 
  array (
    'type' => 'class',
    'classname' => 'Proxy',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\Proxy',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\ProxyEnvironment' => 
  array (
    'type' => 'class',
    'classname' => 'ProxyEnvironment',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\ProxyEnvironment',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\StreamHandler' => 
  array (
    'type' => 'class',
    'classname' => 'StreamHandler',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\StreamHandler',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Handler\\TlsVersion' => 
  array (
    'type' => 'class',
    'classname' => 'TlsVersion',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\TlsVersion',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\HandlerStack' => 
  array (
    'type' => 'class',
    'classname' => 'HandlerStack',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\HandlerStack',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\MessageFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'MessageFormatter',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\MessageFormatter',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\MessageFormatterInterface',
    ),
  ),
  'GuzzleHttp\\Middleware' => 
  array (
    'type' => 'class',
    'classname' => 'Middleware',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Middleware',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Multiplexing' => 
  array (
    'type' => 'class',
    'classname' => 'Multiplexing',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Multiplexing',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Pool' => 
  array (
    'type' => 'class',
    'classname' => 'Pool',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Pool',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromisorInterface',
    ),
  ),
  'GuzzleHttp\\PrepareBodyMiddleware' => 
  array (
    'type' => 'class',
    'classname' => 'PrepareBodyMiddleware',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\PrepareBodyMiddleware',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\RedirectMiddleware' => 
  array (
    'type' => 'class',
    'classname' => 'RedirectMiddleware',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\RedirectMiddleware',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\RequestOptions' => 
  array (
    'type' => 'class',
    'classname' => 'RequestOptions',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\RequestOptions',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\RetryMiddleware' => 
  array (
    'type' => 'class',
    'classname' => 'RetryMiddleware',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\RetryMiddleware',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\TransferStats' => 
  array (
    'type' => 'class',
    'classname' => 'TransferStats',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\TransferStats',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\TransportSharing' => 
  array (
    'type' => 'class',
    'classname' => 'TransportSharing',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\TransportSharing',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Utils' => 
  array (
    'type' => 'class',
    'classname' => 'Utils',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Utils',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\AggregateException' => 
  array (
    'type' => 'class',
    'classname' => 'AggregateException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\AggregateException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\CancellationException' => 
  array (
    'type' => 'class',
    'classname' => 'CancellationException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\CancellationException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\Coroutine' => 
  array (
    'type' => 'class',
    'classname' => 'Coroutine',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Coroutine',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromiseInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\Create' => 
  array (
    'type' => 'class',
    'classname' => 'Create',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Create',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\Each' => 
  array (
    'type' => 'class',
    'classname' => 'Each',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Each',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\EachPromise' => 
  array (
    'type' => 'class',
    'classname' => 'EachPromise',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\EachPromise',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromisorInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\FulfilledPromise' => 
  array (
    'type' => 'class',
    'classname' => 'FulfilledPromise',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\FulfilledPromise',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromiseInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\Is' => 
  array (
    'type' => 'class',
    'classname' => 'Is',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Is',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\Promise' => 
  array (
    'type' => 'class',
    'classname' => 'Promise',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Promise',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromiseInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\RejectedPromise' => 
  array (
    'type' => 'class',
    'classname' => 'RejectedPromise',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\RejectedPromise',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\PromiseInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\RejectionException' => 
  array (
    'type' => 'class',
    'classname' => 'RejectionException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\RejectionException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Promise\\TaskQueue' => 
  array (
    'type' => 'class',
    'classname' => 'TaskQueue',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\TaskQueue',
    'implements' => 
    array (
      0 => 'GuzzleHttp\\Promise\\TaskQueueInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\Utils' => 
  array (
    'type' => 'class',
    'classname' => 'Utils',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\Utils',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\AppendStream' => 
  array (
    'type' => 'class',
    'classname' => 'AppendStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\AppendStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\BufferStream' => 
  array (
    'type' => 'class',
    'classname' => 'BufferStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\BufferStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\CachingStream' => 
  array (
    'type' => 'class',
    'classname' => 'CachingStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\CachingStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\DroppingStream' => 
  array (
    'type' => 'class',
    'classname' => 'DroppingStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\DroppingStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Exception\\MalformedUriException' => 
  array (
    'type' => 'class',
    'classname' => 'MalformedUriException',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7\\Exception',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Exception\\MalformedUriException',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\FnStream' => 
  array (
    'type' => 'class',
    'classname' => 'FnStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\FnStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Header' => 
  array (
    'type' => 'class',
    'classname' => 'Header',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Header',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\HttpFactory' => 
  array (
    'type' => 'class',
    'classname' => 'HttpFactory',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\HttpFactory',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\RequestFactoryInterface',
      1 => 'Psr\\Http\\Message\\ResponseFactoryInterface',
      2 => 'Psr\\Http\\Message\\ServerRequestFactoryInterface',
      3 => 'Psr\\Http\\Message\\StreamFactoryInterface',
      4 => 'Psr\\Http\\Message\\UploadedFileFactoryInterface',
      5 => 'Psr\\Http\\Message\\UriFactoryInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\InflateStream' => 
  array (
    'type' => 'class',
    'classname' => 'InflateStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\InflateStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\LazyOpenStream' => 
  array (
    'type' => 'class',
    'classname' => 'LazyOpenStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\LazyOpenStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\LimitStream' => 
  array (
    'type' => 'class',
    'classname' => 'LimitStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\LimitStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Message' => 
  array (
    'type' => 'class',
    'classname' => 'Message',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Message',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\MimeType' => 
  array (
    'type' => 'class',
    'classname' => 'MimeType',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\MimeType',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\MultipartStream' => 
  array (
    'type' => 'class',
    'classname' => 'MultipartStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\MultipartStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\NoSeekStream' => 
  array (
    'type' => 'class',
    'classname' => 'NoSeekStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\NoSeekStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\PumpStream' => 
  array (
    'type' => 'class',
    'classname' => 'PumpStream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\PumpStream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Query' => 
  array (
    'type' => 'class',
    'classname' => 'Query',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Query',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\Request' => 
  array (
    'type' => 'class',
    'classname' => 'Request',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Request',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\RequestInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Response' => 
  array (
    'type' => 'class',
    'classname' => 'Response',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Response',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\ResponseInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Rfc3986' => 
  array (
    'type' => 'class',
    'classname' => 'Rfc3986',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Rfc3986',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\Rfc7230' => 
  array (
    'type' => 'class',
    'classname' => 'Rfc7230',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Rfc7230',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\ServerRequest' => 
  array (
    'type' => 'class',
    'classname' => 'ServerRequest',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\ServerRequest',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\ServerRequestInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Stream' => 
  array (
    'type' => 'class',
    'classname' => 'Stream',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Stream',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\StreamWrapper' => 
  array (
    'type' => 'class',
    'classname' => 'StreamWrapper',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\StreamWrapper',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\UploadedFile' => 
  array (
    'type' => 'class',
    'classname' => 'UploadedFile',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\UploadedFile',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\UploadedFileInterface',
    ),
  ),
  'GuzzleHttp\\Psr7\\Uri' => 
  array (
    'type' => 'class',
    'classname' => 'Uri',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Uri',
    'implements' => 
    array (
      0 => 'Psr\\Http\\Message\\UriInterface',
      1 => 'JsonSerializable',
    ),
  ),
  'GuzzleHttp\\Psr7\\UriComparator' => 
  array (
    'type' => 'class',
    'classname' => 'UriComparator',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\UriComparator',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\UriNormalizer' => 
  array (
    'type' => 'class',
    'classname' => 'UriNormalizer',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\UriNormalizer',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\UriResolver' => 
  array (
    'type' => 'class',
    'classname' => 'UriResolver',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\UriResolver',
    'implements' => 
    array (
    ),
  ),
  'GuzzleHttp\\Psr7\\Utils' => 
  array (
    'type' => 'class',
    'classname' => 'Utils',
    'isabstract' => false,
    'namespace' => 'GuzzleHttp\\Psr7',
    'extends' => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\Utils',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Attribute\\AsMonologProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'AsMonologProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Attribute',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Attribute\\AsMonologProcessor',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\DateTimeImmutable' => 
  array (
    'type' => 'class',
    'classname' => 'DateTimeImmutable',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\DateTimeImmutable',
    'implements' => 
    array (
      0 => 'JsonSerializable',
    ),
  ),
  'Monolog\\ErrorHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ErrorHandler',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\ErrorHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\ChromePHPFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'ChromePHPFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\ChromePHPFormatter',
    'implements' => 
    array (
      0 => 'Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Formatter\\ElasticaFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'ElasticaFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\ElasticaFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\ElasticsearchFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'ElasticsearchFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\ElasticsearchFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\FlowdockFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'FlowdockFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\FlowdockFormatter',
    'implements' => 
    array (
      0 => 'Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Formatter\\FluentdFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'FluentdFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\FluentdFormatter',
    'implements' => 
    array (
      0 => 'Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Formatter\\GelfMessageFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'GelfMessageFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\GelfMessageFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\GoogleCloudLoggingFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'GoogleCloudLoggingFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\GoogleCloudLoggingFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\HtmlFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'HtmlFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\HtmlFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\JsonFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'JsonFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\JsonFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\LineFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'LineFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\LineFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\LogglyFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'LogglyFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\LogglyFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\LogmaticFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'LogmaticFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\LogmaticFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\LogstashFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'LogstashFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\LogstashFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\MongoDBFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'MongoDBFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\MongoDBFormatter',
    'implements' => 
    array (
      0 => 'Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Formatter\\NormalizerFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'NormalizerFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\NormalizerFormatter',
    'implements' => 
    array (
      0 => 'Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Formatter\\ScalarFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'ScalarFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\ScalarFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Formatter\\WildfireFormatter' => 
  array (
    'type' => 'class',
    'classname' => 'WildfireFormatter',
    'isabstract' => false,
    'namespace' => 'Monolog\\Formatter',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\WildfireFormatter',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\AbstractHandler' => 
  array (
    'type' => 'class',
    'classname' => 'AbstractHandler',
    'isabstract' => true,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\AbstractHandler',
    'implements' => 
    array (
      0 => 'Monolog\\ResettableInterface',
    ),
  ),
  'Monolog\\Handler\\AbstractProcessingHandler' => 
  array (
    'type' => 'class',
    'classname' => 'AbstractProcessingHandler',
    'isabstract' => true,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\AbstractProcessingHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\AbstractSyslogHandler' => 
  array (
    'type' => 'class',
    'classname' => 'AbstractSyslogHandler',
    'isabstract' => true,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\AbstractSyslogHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\AmqpHandler' => 
  array (
    'type' => 'class',
    'classname' => 'AmqpHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\AmqpHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\BrowserConsoleHandler' => 
  array (
    'type' => 'class',
    'classname' => 'BrowserConsoleHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\BrowserConsoleHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\BufferHandler' => 
  array (
    'type' => 'class',
    'classname' => 'BufferHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\BufferHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\ChromePHPHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ChromePHPHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ChromePHPHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\CouchDBHandler' => 
  array (
    'type' => 'class',
    'classname' => 'CouchDBHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\CouchDBHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\CubeHandler' => 
  array (
    'type' => 'class',
    'classname' => 'CubeHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\CubeHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\Curl\\Util' => 
  array (
    'type' => 'class',
    'classname' => 'Util',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler\\Curl',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\Curl\\Util',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\DeduplicationHandler' => 
  array (
    'type' => 'class',
    'classname' => 'DeduplicationHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\DeduplicationHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\DoctrineCouchDBHandler' => 
  array (
    'type' => 'class',
    'classname' => 'DoctrineCouchDBHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\DoctrineCouchDBHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\DynamoDbHandler' => 
  array (
    'type' => 'class',
    'classname' => 'DynamoDbHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\DynamoDbHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\ElasticaHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ElasticaHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ElasticaHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\ElasticsearchHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ElasticsearchHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ElasticsearchHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\ErrorLogHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ErrorLogHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ErrorLogHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\FallbackGroupHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FallbackGroupHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FallbackGroupHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\FilterHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FilterHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FilterHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\ResettableInterface',
      2 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\FingersCrossed\\ChannelLevelActivationStrategy' => 
  array (
    'type' => 'class',
    'classname' => 'ChannelLevelActivationStrategy',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler\\FingersCrossed',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FingersCrossed\\ChannelLevelActivationStrategy',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\FingersCrossed\\ActivationStrategyInterface',
    ),
  ),
  'Monolog\\Handler\\FingersCrossed\\ErrorLevelActivationStrategy' => 
  array (
    'type' => 'class',
    'classname' => 'ErrorLevelActivationStrategy',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler\\FingersCrossed',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FingersCrossed\\ErrorLevelActivationStrategy',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\FingersCrossed\\ActivationStrategyInterface',
    ),
  ),
  'Monolog\\Handler\\FingersCrossedHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FingersCrossedHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FingersCrossedHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\ResettableInterface',
      2 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\FirePHPHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FirePHPHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FirePHPHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\FleepHookHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FleepHookHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FleepHookHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\FlowdockHandler' => 
  array (
    'type' => 'class',
    'classname' => 'FlowdockHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FlowdockHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\GelfHandler' => 
  array (
    'type' => 'class',
    'classname' => 'GelfHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\GelfHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\GroupHandler' => 
  array (
    'type' => 'class',
    'classname' => 'GroupHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\GroupHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\ResettableInterface',
    ),
  ),
  'Monolog\\Handler\\Handler' => 
  array (
    'type' => 'class',
    'classname' => 'Handler',
    'isabstract' => true,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\Handler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\HandlerInterface',
    ),
  ),
  'Monolog\\Handler\\HandlerWrapper' => 
  array (
    'type' => 'class',
    'classname' => 'HandlerWrapper',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\HandlerWrapper',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\HandlerInterface',
      1 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      2 => 'Monolog\\Handler\\FormattableHandlerInterface',
      3 => 'Monolog\\ResettableInterface',
    ),
  ),
  'Monolog\\Handler\\IFTTTHandler' => 
  array (
    'type' => 'class',
    'classname' => 'IFTTTHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\IFTTTHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\InsightOpsHandler' => 
  array (
    'type' => 'class',
    'classname' => 'InsightOpsHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\InsightOpsHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\LogEntriesHandler' => 
  array (
    'type' => 'class',
    'classname' => 'LogEntriesHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\LogEntriesHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\LogglyHandler' => 
  array (
    'type' => 'class',
    'classname' => 'LogglyHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\LogglyHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\LogmaticHandler' => 
  array (
    'type' => 'class',
    'classname' => 'LogmaticHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\LogmaticHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\MailHandler' => 
  array (
    'type' => 'class',
    'classname' => 'MailHandler',
    'isabstract' => true,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\MailHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\MandrillHandler' => 
  array (
    'type' => 'class',
    'classname' => 'MandrillHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\MandrillHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\MissingExtensionException' => 
  array (
    'type' => 'class',
    'classname' => 'MissingExtensionException',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\MissingExtensionException',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\MongoDBHandler' => 
  array (
    'type' => 'class',
    'classname' => 'MongoDBHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\MongoDBHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\NativeMailerHandler' => 
  array (
    'type' => 'class',
    'classname' => 'NativeMailerHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\NativeMailerHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\NewRelicHandler' => 
  array (
    'type' => 'class',
    'classname' => 'NewRelicHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\NewRelicHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\NoopHandler' => 
  array (
    'type' => 'class',
    'classname' => 'NoopHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\NoopHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\NullHandler' => 
  array (
    'type' => 'class',
    'classname' => 'NullHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\NullHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\OverflowHandler' => 
  array (
    'type' => 'class',
    'classname' => 'OverflowHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\OverflowHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\PHPConsoleHandler' => 
  array (
    'type' => 'class',
    'classname' => 'PHPConsoleHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\PHPConsoleHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\ProcessHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ProcessHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ProcessHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\PsrHandler' => 
  array (
    'type' => 'class',
    'classname' => 'PsrHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\PsrHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\PushoverHandler' => 
  array (
    'type' => 'class',
    'classname' => 'PushoverHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\PushoverHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\RedisHandler' => 
  array (
    'type' => 'class',
    'classname' => 'RedisHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\RedisHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\RedisPubSubHandler' => 
  array (
    'type' => 'class',
    'classname' => 'RedisPubSubHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\RedisPubSubHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\RollbarHandler' => 
  array (
    'type' => 'class',
    'classname' => 'RollbarHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\RollbarHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\RotatingFileHandler' => 
  array (
    'type' => 'class',
    'classname' => 'RotatingFileHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\RotatingFileHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SamplingHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SamplingHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SamplingHandler',
    'implements' => 
    array (
      0 => 'Monolog\\Handler\\ProcessableHandlerInterface',
      1 => 'Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\SendGridHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SendGridHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SendGridHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\Slack\\SlackRecord' => 
  array (
    'type' => 'class',
    'classname' => 'SlackRecord',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler\\Slack',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\Slack\\SlackRecord',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SlackHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SlackHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SlackHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SlackWebhookHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SlackWebhookHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SlackWebhookHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SocketHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SocketHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SocketHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SqsHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SqsHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SqsHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\StreamHandler' => 
  array (
    'type' => 'class',
    'classname' => 'StreamHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\StreamHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SwiftMailerHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SwiftMailerHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SwiftMailerHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SymfonyMailerHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SymfonyMailerHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SymfonyMailerHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SyslogHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SyslogHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SyslogHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SyslogUdp\\UdpSocket' => 
  array (
    'type' => 'class',
    'classname' => 'UdpSocket',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler\\SyslogUdp',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SyslogUdp\\UdpSocket',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\SyslogUdpHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SyslogUdpHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\SyslogUdpHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\TelegramBotHandler' => 
  array (
    'type' => 'class',
    'classname' => 'TelegramBotHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\TelegramBotHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\TestHandler' => 
  array (
    'type' => 'class',
    'classname' => 'TestHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\TestHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\WhatFailureGroupHandler' => 
  array (
    'type' => 'class',
    'classname' => 'WhatFailureGroupHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\WhatFailureGroupHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Handler\\ZendMonitorHandler' => 
  array (
    'type' => 'class',
    'classname' => 'ZendMonitorHandler',
    'isabstract' => false,
    'namespace' => 'Monolog\\Handler',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ZendMonitorHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Logger' => 
  array (
    'type' => 'class',
    'classname' => 'Logger',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Logger',
    'implements' => 
    array (
      0 => 'Psr\\Log\\LoggerInterface',
      1 => 'Monolog\\ResettableInterface',
    ),
  ),
  'Monolog\\Processor\\GitProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'GitProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\GitProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\HostnameProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'HostnameProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\HostnameProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\IntrospectionProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'IntrospectionProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\IntrospectionProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\MemoryPeakUsageProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'MemoryPeakUsageProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\MemoryPeakUsageProcessor',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Processor\\MemoryProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'MemoryProcessor',
    'isabstract' => true,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\MemoryProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\MemoryUsageProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'MemoryUsageProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\MemoryUsageProcessor',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Processor\\MercurialProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'MercurialProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\MercurialProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\ProcessIdProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'ProcessIdProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\ProcessIdProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\PsrLogMessageProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'PsrLogMessageProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\PsrLogMessageProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\TagProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'TagProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\TagProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Processor\\UidProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'UidProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\UidProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
      1 => 'Monolog\\ResettableInterface',
    ),
  ),
  'Monolog\\Processor\\WebProcessor' => 
  array (
    'type' => 'class',
    'classname' => 'WebProcessor',
    'isabstract' => false,
    'namespace' => 'Monolog\\Processor',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\WebProcessor',
    'implements' => 
    array (
      0 => 'Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\Registry' => 
  array (
    'type' => 'class',
    'classname' => 'Registry',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Registry',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\SignalHandler' => 
  array (
    'type' => 'class',
    'classname' => 'SignalHandler',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\SignalHandler',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Test\\TestCase' => 
  array (
    'type' => 'class',
    'classname' => 'TestCase',
    'isabstract' => false,
    'namespace' => 'Monolog\\Test',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Test\\TestCase',
    'implements' => 
    array (
    ),
  ),
  'Monolog\\Utils' => 
  array (
    'type' => 'class',
    'classname' => 'Utils',
    'isabstract' => false,
    'namespace' => 'Monolog',
    'extends' => 'CheckoutComWC\\Vendor\\Monolog\\Utils',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\AbstractLogger' => 
  array (
    'type' => 'class',
    'classname' => 'AbstractLogger',
    'isabstract' => true,
    'namespace' => 'Psr\\Log',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\AbstractLogger',
    'implements' => 
    array (
      0 => 'Psr\\Log\\LoggerInterface',
    ),
  ),
  'Psr\\Log\\InvalidArgumentException' => 
  array (
    'type' => 'class',
    'classname' => 'InvalidArgumentException',
    'isabstract' => false,
    'namespace' => 'Psr\\Log',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\InvalidArgumentException',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\LogLevel' => 
  array (
    'type' => 'class',
    'classname' => 'LogLevel',
    'isabstract' => false,
    'namespace' => 'Psr\\Log',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\LogLevel',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\NullLogger' => 
  array (
    'type' => 'class',
    'classname' => 'NullLogger',
    'isabstract' => false,
    'namespace' => 'Psr\\Log',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\NullLogger',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\Test\\DummyTest' => 
  array (
    'type' => 'class',
    'classname' => 'DummyTest',
    'isabstract' => false,
    'namespace' => 'Psr\\Log\\Test',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\Test\\DummyTest',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\Test\\LoggerInterfaceTest' => 
  array (
    'type' => 'class',
    'classname' => 'LoggerInterfaceTest',
    'isabstract' => true,
    'namespace' => 'Psr\\Log\\Test',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\Test\\LoggerInterfaceTest',
    'implements' => 
    array (
    ),
  ),
  'Psr\\Log\\Test\\TestLogger' => 
  array (
    'type' => 'class',
    'classname' => 'TestLogger',
    'isabstract' => false,
    'namespace' => 'Psr\\Log\\Test',
    'extends' => 'CheckoutComWC\\Vendor\\Psr\\Log\\Test\\TestLogger',
    'implements' => 
    array (
    ),
  ),
  'Symfony\\Polyfill\\Php80\\Php80' => 
  array (
    'type' => 'class',
    'classname' => 'Php80',
    'isabstract' => false,
    'namespace' => 'Symfony\\Polyfill\\Php80',
    'extends' => 'CheckoutComWC\\Vendor\\Symfony\\Polyfill\\Php80\\Php80',
    'implements' => 
    array (
    ),
  ),
  'Symfony\\Polyfill\\Php80\\PhpToken' => 
  array (
    'type' => 'class',
    'classname' => 'PhpToken',
    'isabstract' => false,
    'namespace' => 'Symfony\\Polyfill\\Php80',
    'extends' => 'CheckoutComWC\\Vendor\\Symfony\\Polyfill\\Php80\\PhpToken',
    'implements' => 
    array (
      0 => 'Stringable',
    ),
  ),
  'GuzzleHttp\\ClientTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'ClientTrait',
    'namespace' => 'GuzzleHttp',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\ClientTrait',
    ),
  ),
  'GuzzleHttp\\Psr7\\MessageTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'MessageTrait',
    'namespace' => 'GuzzleHttp\\Psr7',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\MessageTrait',
    ),
  ),
  'GuzzleHttp\\Psr7\\StreamDecoratorTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'StreamDecoratorTrait',
    'namespace' => 'GuzzleHttp\\Psr7',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Psr7\\StreamDecoratorTrait',
    ),
  ),
  'Monolog\\Handler\\FormattableHandlerTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'FormattableHandlerTrait',
    'namespace' => 'Monolog\\Handler',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FormattableHandlerTrait',
    ),
  ),
  'Monolog\\Handler\\ProcessableHandlerTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'ProcessableHandlerTrait',
    'namespace' => 'Monolog\\Handler',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ProcessableHandlerTrait',
    ),
  ),
  'Monolog\\Handler\\WebRequestRecognizerTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'WebRequestRecognizerTrait',
    'namespace' => 'Monolog\\Handler',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\WebRequestRecognizerTrait',
    ),
  ),
  'Psr\\Log\\LoggerAwareTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'LoggerAwareTrait',
    'namespace' => 'Psr\\Log',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Log\\LoggerAwareTrait',
    ),
  ),
  'Psr\\Log\\LoggerTrait' => 
  array (
    'type' => 'trait',
    'traitname' => 'LoggerTrait',
    'namespace' => 'Psr\\Log',
    'use' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Log\\LoggerTrait',
    ),
  ),
  'GuzzleHttp\\BodySummarizerInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'BodySummarizerInterface',
    'namespace' => 'GuzzleHttp',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\BodySummarizerInterface',
    ),
  ),
  'GuzzleHttp\\ClientInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ClientInterface',
    'namespace' => 'GuzzleHttp',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\ClientInterface',
    ),
  ),
  'GuzzleHttp\\Cookie\\CookieJarInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'CookieJarInterface',
    'namespace' => 'GuzzleHttp\\Cookie',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Cookie\\CookieJarInterface',
    ),
  ),
  'GuzzleHttp\\Exception\\GuzzleException' => 
  array (
    'type' => 'interface',
    'interfacename' => 'GuzzleException',
    'namespace' => 'GuzzleHttp\\Exception',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Exception\\GuzzleException',
    ),
  ),
  'GuzzleHttp\\Handler\\CurlFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'CurlFactoryInterface',
    'namespace' => 'GuzzleHttp\\Handler',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Handler\\CurlFactoryInterface',
    ),
  ),
  'GuzzleHttp\\MessageFormatterInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'MessageFormatterInterface',
    'namespace' => 'GuzzleHttp',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\MessageFormatterInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\PromiseInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'PromiseInterface',
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\PromiseInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\PromisorInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'PromisorInterface',
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\PromisorInterface',
    ),
  ),
  'GuzzleHttp\\Promise\\TaskQueueInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'TaskQueueInterface',
    'namespace' => 'GuzzleHttp\\Promise',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\GuzzleHttp\\Promise\\TaskQueueInterface',
    ),
  ),
  'Monolog\\Formatter\\FormatterInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'FormatterInterface',
    'namespace' => 'Monolog\\Formatter',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Formatter\\FormatterInterface',
    ),
  ),
  'Monolog\\Handler\\FingersCrossed\\ActivationStrategyInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ActivationStrategyInterface',
    'namespace' => 'Monolog\\Handler\\FingersCrossed',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FingersCrossed\\ActivationStrategyInterface',
    ),
  ),
  'Monolog\\Handler\\FormattableHandlerInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'FormattableHandlerInterface',
    'namespace' => 'Monolog\\Handler',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\FormattableHandlerInterface',
    ),
  ),
  'Monolog\\Handler\\HandlerInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'HandlerInterface',
    'namespace' => 'Monolog\\Handler',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\HandlerInterface',
    ),
  ),
  'Monolog\\Handler\\ProcessableHandlerInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ProcessableHandlerInterface',
    'namespace' => 'Monolog\\Handler',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Handler\\ProcessableHandlerInterface',
    ),
  ),
  'Monolog\\LogRecord' => 
  array (
    'type' => 'interface',
    'interfacename' => 'LogRecord',
    'namespace' => 'Monolog',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\LogRecord',
    ),
  ),
  'Monolog\\Processor\\ProcessorInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ProcessorInterface',
    'namespace' => 'Monolog\\Processor',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\Processor\\ProcessorInterface',
    ),
  ),
  'Monolog\\ResettableInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ResettableInterface',
    'namespace' => 'Monolog',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Monolog\\ResettableInterface',
    ),
  ),
  'Psr\\Http\\Client\\ClientExceptionInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ClientExceptionInterface',
    'namespace' => 'Psr\\Http\\Client',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Client\\ClientExceptionInterface',
    ),
  ),
  'Psr\\Http\\Client\\ClientInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ClientInterface',
    'namespace' => 'Psr\\Http\\Client',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Client\\ClientInterface',
    ),
  ),
  'Psr\\Http\\Client\\NetworkExceptionInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'NetworkExceptionInterface',
    'namespace' => 'Psr\\Http\\Client',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Client\\NetworkExceptionInterface',
    ),
  ),
  'Psr\\Http\\Client\\RequestExceptionInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'RequestExceptionInterface',
    'namespace' => 'Psr\\Http\\Client',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Client\\RequestExceptionInterface',
    ),
  ),
  'Psr\\Http\\Message\\RequestFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'RequestFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\RequestFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\ResponseFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ResponseFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\ResponseFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\ServerRequestFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ServerRequestFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\ServerRequestFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\StreamFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'StreamFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\StreamFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\UploadedFileFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'UploadedFileFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\UploadedFileFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\UriFactoryInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'UriFactoryInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\UriFactoryInterface',
    ),
  ),
  'Psr\\Http\\Message\\MessageInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'MessageInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\MessageInterface',
    ),
  ),
  'Psr\\Http\\Message\\RequestInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'RequestInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\RequestInterface',
    ),
  ),
  'Psr\\Http\\Message\\ResponseInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ResponseInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\ResponseInterface',
    ),
  ),
  'Psr\\Http\\Message\\ServerRequestInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'ServerRequestInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\ServerRequestInterface',
    ),
  ),
  'Psr\\Http\\Message\\StreamInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'StreamInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\StreamInterface',
    ),
  ),
  'Psr\\Http\\Message\\UploadedFileInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'UploadedFileInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\UploadedFileInterface',
    ),
  ),
  'Psr\\Http\\Message\\UriInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'UriInterface',
    'namespace' => 'Psr\\Http\\Message',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Http\\Message\\UriInterface',
    ),
  ),
  'Psr\\Log\\LoggerAwareInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'LoggerAwareInterface',
    'namespace' => 'Psr\\Log',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Log\\LoggerAwareInterface',
    ),
  ),
  'Psr\\Log\\LoggerInterface' => 
  array (
    'type' => 'interface',
    'interfacename' => 'LoggerInterface',
    'namespace' => 'Psr\\Log',
    'extends' => 
    array (
      0 => 'CheckoutComWC\\Vendor\\Psr\\Log\\LoggerInterface',
    ),
  ),
);

        public function __construct()
        {
            $this->includeFilePath = __DIR__ . '/autoload_alias.php';
        }

        /**
         * @param string $class
         */
        public function autoload($class): void
        {
            if (!isset($this->autoloadAliases[$class])) {
                return;
            }
            switch ($this->autoloadAliases[$class]['type']) {
                case 'class':
                        $this->load(
                            $this->classTemplate(
                                $this->autoloadAliases[$class]
                            )
                        );
                    break;
                case 'interface':
                    $this->load(
                        $this->interfaceTemplate(
                            $this->autoloadAliases[$class]
                        )
                    );
                    break;
                case 'trait':
                    $this->load(
                        $this->traitTemplate(
                            $this->autoloadAliases[$class]
                        )
                    );
                    break;
                case 'enum':
                    // Enums are final so cannot be aliased with an `extends` shim like classes are; `class_alias()`
                    // makes the original name a true alias of the renamed enum, preserving case identity (`===`),
                    // `match` arms, `::cases()`, `::from()` and `instanceof` the enum itself. Unlike the class shim,
                    // the alias does not implement the enum's original interface names, so `instanceof` against an
                    // original interface name is false. Enums require PHP 8.1, but this only executes when an enum
                    // is autoloaded by its original name, i.e. on a runtime already using enums.
                    \class_alias(
                        $this->autoloadAliases[$class]['concrete'],
                        $class
                    );
                    break;
                default:
                    // Never.
                    break;
            }
        }

        private function load(string $includeFile): void
        {
            file_put_contents($this->includeFilePath, $includeFile);
            include $this->includeFilePath;
            file_exists($this->includeFilePath) && unlink($this->includeFilePath);
        }

        /**
         * @param ClassAliasArray $class
         */
        private function classTemplate(array $class): string
        {
            $abstract = $class['isabstract'] ? 'abstract ' : '';
            $classname = $class['classname'];
            if (isset($class['namespace'])) {
                $namespace = "namespace {$class['namespace']};";
                $extends = '\\' . $class['extends'];
                $implements = empty($class['implements']) ? ''
                : ' implements \\' . implode(', \\', $class['implements']);
            } else {
                $namespace = '';
                $extends = $class['extends'];
                $implements = !empty($class['implements']) ? ''
                : ' implements ' . implode(', ', $class['implements']);
            }
            return <<<EOD
                <?php
                $namespace
                $abstract class $classname extends $extends $implements {}
                EOD;
        }

        /**
         * @param InterfaceAliasArray $interface
         */
        private function interfaceTemplate(array $interface): string
        {
            $interfacename = $interface['interfacename'];
            $namespace = isset($interface['namespace'])
            ? "namespace {$interface['namespace']};" : '';
            $extends = isset($interface['namespace'])
            ? '\\' . implode('\\ ,', $interface['extends'])
            : implode(', ', $interface['extends']);
            return <<<EOD
                <?php
                $namespace
                interface $interfacename extends $extends {}
                EOD;
        }

        /**
         * @param TraitAliasArray $trait
         */
        private function traitTemplate(array $trait): string
        {
            $traitname = $trait['traitname'];
            $namespace = isset($trait['namespace'])
            ? "namespace {$trait['namespace']};" : '';
            $uses = isset($trait['namespace'])
            ? '\\' . implode(';' . PHP_EOL . '    use \\', $trait['use'])
            : implode(';' . PHP_EOL . '    use ', $trait['use']);
            return <<<EOD
                <?php
                $namespace
                trait $traitname { 
                    use $uses; 
                }
                EOD;
        }
    }

    spl_autoload_register([ new AliasAutoloader(), 'autoload' ]);
}
