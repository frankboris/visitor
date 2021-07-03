<?php

namespace Shetabit\Visitor;

use Browser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Shetabit\Visitor\Contracts\UserAgentParser;
use Shetabit\Visitor\Models\Visit;

class Visitor implements UserAgentParser
{
    /**
     * except.
     *
     * @var array
     */
    protected $except ;
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Driver name.
     *
     * @var string
     */
    protected $driver;

    /**
     * Driver instance.
     *
     * @var object
     */
    protected $driverInstance;

    /**
     * Request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * Visitor (user) instance.
     *
     * @var Model|null
     */
    protected $visitor;

    /**
     * Visitor constructor.
     *
     * @param $config
     *
     * @throws \Exception
     */
    public function __construct(Request $request, $config)
    {
        $this->request = $request;
        $this->config = $config;
        $this->except = $config['except'];
        $this->setVisitor($request->user());
    }

    /**
     * Retrieve request's data
     *
     * @return array
     */
    public function request() : array
    {
        return $this->request->all();
    }

    /**
     * Retrieve user's ip.
     *
     * @return string|null
     */
    public  function ip() : ?string
    {
        return $this->request->ip();
    }

    /**
     * Retrieve request's url
     *
     * @return string
     */
    public function url() : string
    {
        return $this->request->fullUrl();
    }

    /**
     * Retrieve request's referer
     *
     * @return string|null
     */
    public function referer() : ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Retrieve request's method.
     *
     * @return string
     */
    public function method() : string
    {
        return $this->request->getMethod();
    }

    /**
     * Retrieve http headers.
     *
     * @return array
     */
    public function httpHeaders() : array
    {
        return $this->request->headers->all();
    }

    /**
     * Retrieve agent.
     *
     * @return string
     */
    public function userAgent() : string
    {
        return $this->request->userAgent() ?? '';
    }

    /**
     * Retrieve device's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function device() : string
    {
        return Browser::deviceFamily();
    }

    /**
     * Retrieve platform's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function platform() : string
    {
        return Browser::platformFamily();
    }

    /**
     * Retrieve platform's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function platform_version() : string
    {
        return Browser::platformName();
    }

    /**
     * Retrieve browser's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function browser() : string
    {
        return Browser::browserFamily();
    }

    /**
     * Retrieve languages.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function language() : string
    {
        return $this->request->getLocale();
    }

    /**
     * Set visitor (user)
     *
     * @param Model|null $user
     *
     * @return $this
     */
    public function setVisitor(?Model $user)
    {
        $this->visitor = $user;

        return $this;
    }

    /**
     * Retrieve visitor (user)
     *
     * @return Model|null
     */
    public function getVisitor() : ?Model
    {
        return $this->visitor;
    }

    /**
     * Create a visit log.
     *
     * @param Model $model
     */
    public function visit(Model $model = null)
    {
        $data = $this->prepareLog();
        if(in_array($this->request->path(),$this->except)){
            unset($data['request']);
        }

        if (method_exists($model, 'visitLogs')) {
            $visit = $model->visitLogs()->create($data);
        } else {
            $visit = Visit::create($data);
        }

        return $visit;
    }

    /**
     * Retrieve online visitors.
     *
     * @param string $model
     * @param int $seconds
     */
    public function onlineVisitors(string $model, $seconds = 180)
    {
        return app($model)->online()->get();
    }

    /**
     * Determine if given visitor or current one is online.
     *
     * @param Model|null $visitor
     * @param int $seconds
     *
     * @return bool
     */
    public function isOnline(?Model $visitor = null, $seconds = 180)
    {
        $time = now()->subSeconds($seconds);

        $visitor = $visitor ?? $this->getVisitor();

        if (empty($visitor)) {
            return false;
        }

        return Visit::whereHasMorph('visitor', get_class($visitor), function ($query) use ($visitor, $time) {
            $query->where('visitor_id', $visitor->id);
        })->whereDate('created_at', '>=', $time)->count() > 0;
    }

    /**
     * Prepare log's data.
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function prepareLog() : array
    {
        return [
            'method' => $this->method(),
            'request' => $this->request(),
            'url' => $this->url(),
            'referer' => $this->referer(),
            'language' => $this->language(),
            'useragent' => $this->userAgent(),
            'headers' => $this->httpHeaders(),
            'device' => $this->device(),
            'platform' => $this->platform(),
            'platform_version' => $this->platform_version(),
            'browser' => $this->browser(),
            'ip' => $this->ip(),
            'visitor_id' => $this->getVisitor() ? $this->getVisitor()->id : null,
            'visitor_type' => $this->getVisitor() ? get_class($this->getVisitor()): null
        ];
    }
}
