<?php

namespace Nsm\Bundle\ApiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Nsm\Bundle\ApiBundle\Form\Model\DateRange;

class AbstractQueryBuilder extends QueryBuilder
{
    /**
     * @var AbstractRepository $repo
     */
    protected $repo;

    /**
     * @var string $entityAlias
     */
    protected $entityAlias;

    /**
     * @param EntityManager $em
     * @param AbstractRepository $repository
     * @param null $alias
     */
    public function __construct(EntityManager $em, AbstractRepository $repository, $alias = null)
    {
        parent::__construct($em);

        $this->repository  = $repository;
        $this->entityAlias = (null !== $alias) ? $alias : $this->getEntityAlias();
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Get related entity class name
     *
     * @return string
     */
    protected function getEntityClassName()
    {
        return $this->getRepository()->getClassName();
    }

    /**
     * Get related entity alias used in query
     * Will be used entity class name without namespace
     *
     * @return string
     */
    public function getEntityAlias()
    {
        $className = explode('\\', $this->getEntityClassName());

        return end($className);
    }

    /**
     * Select the
     * @return $this
     */
    public function selectRootEntity()
    {
        $this->select($this->getEntityAlias());
        $this->from($this->getEntityClassName(), $this->getEntityAlias());

        return $this;
    }

    /**
     * @param array $criteria
     * @return $this
     */
    public function filterByCriteria(array $criteria)
    {
        foreach ($criteria as $key => $value) {

            $method = 'filterBy' . ucfirst($key);
            call_user_func(array($this, $method), $value);
        }

        return $this;
    }

    /**
     * @param $id
     * @return $this
     */
    public function filterById($id)
    {
        $this->addWhere("id", $id);

        return $this;
    }

    /**
     * @param $columnName
     * @param $value
     * @param bool $inclusive
     * @param string $method
     * @return $this
     */
    public function addWhere($columnName, $value, $inclusive = true, $method = "andWhere")
    {
        $parameterCount = count($this->getParameters()) + 1;

        if (false === strpos($columnName, ".")) {
            $columnName = $this->getEntityAlias() . "." . $columnName;
        }

        switch (true) {

            /** @var DateRange $value */
            case($value instanceof DateRange) :

                $columnName = substr($columnName, 0, -5);
                $startExpr = null;
                $endExpr = null;

                if (null !== $value->getStart()) {
                    $startExpr = $this->expr()->gte($columnName, '?' . $parameterCount);
                    $this->setParameter($parameterCount, $value->getStart());
                    $parameterCount += 1;
                }

                if (null !== $value->getEnd()) {
                    $endExpr = $this->expr()->lte($columnName, '?' . $parameterCount);
                    $this->setParameter($parameterCount, $value->getEnd());
                    $parameterCount += 1;
                }

                $clause = $this->expr()->andX($startExpr, $endExpr);
                
                break;

            case(true === is_array($value) || $value instanceof \Iterator) :
                $comparison = (true == $inclusive) ? "in" : "notIn";
                // Todo: $value may be an array of entitues.
                // array_unique returns the string value which is probably not an integer
                $clause     = $this->expr()->$comparison($columnName, array_unique($value, SORT_REGULAR));
                break;
            
            case ($value === 'isNull' || $value === 'isNotNull') :
                $clause = $this->expr()->$value($columnName);
                break;

            default:
                $comparison = (true == $inclusive) ? "=" : "<>";
                $clause     = sprintf('%s %s %s', $columnName, $comparison, "?" . $parameterCount);
                $this->setParameter($parameterCount, $value);
        }

        $this->$method($clause);

        return $this;
    }

    /**
     * @param $method
     * @param $arguments
     * @return $this
     */
    public function __call($method, $arguments)
    {
        if (false !== strpos($method, "filterBy")) {

            $value = $arguments[0];
            $this->addWhere(lcfirst(substr($method, 8)), $value);

            return $this;
        }

        return parent::__call($method, $arguments);
    }
}
