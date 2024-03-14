<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Provider\DataSet;

/**
 * @property-read string $name Name of the provision category or provider
 * @property-read string $description Description of the provision category or provider
 * @property-read string|null $logo_url Logo image URL
 * @property-read string|null $icon Icon name e.g., 'world' or 'server'
 */
class AboutData extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'name' => ['string', 'required', 'max:50'],
            'description' => ['string', 'required', 'max:300'],
            'logo_url' => ['nullable', 'url'],
            'icon' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return self $this
     */
    public function setName(string $name): AboutData
    {
        $this->setValue('name', $name);

        return $this;
    }

    /**
     * @return self $this
     */
    public function setDescription(string $description): AboutData
    {
        $this->setValue('description', $description);

        return $this;
    }

    /**
     * @return self $this
     */
    public function setLogoUrl(?string $url): AboutData
    {
        $this->setValue('logo_url', $url);

        return $this;
    }

    /**
     * @return self $this
     */
    public function setIcon(?string $icon): AboutData
    {
        $this->setValue('icon', $icon);

        return $this;
    }
}
