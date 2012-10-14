<?php

namespace FSC\HateoasBundle\Tests\Fixtures;

use FSC\HateoasBundle\Annotation as Hateoas;

/**
 * @Hateoas\Link("self", route = "_some_route", params = { "identifier" = "id"})
 * @Hateoas\Link("users", route = "_users")
 * @Hateoas\Link("home", route = "homepage")
 */
class User
{
    private $id;
    private $username;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }
}