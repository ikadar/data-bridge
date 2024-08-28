<?php

namespace App\Adapter\Neo4j;

use App\Entity\IntermediateFormat;
use App\Exception\ExistingEntityIsNotAllowedException;
use App\Exception\NewEntityIsNotAllowedException;

class Neo4jAdapter
{
    protected array $configuration;
    protected IntermediateFormat $intermediateData;

    public function __construct(IntermediateFormat $intermediateData)
    {
        $this->intermediateData = $intermediateData;

        $this->configuration = [
            "entityTypes" => [
                "Client_Client" => [
                    "isNewAllowed" => true,
                    "isExistingAllowed" => true,
                    "key" => ["name"],
                    "attributes" => [
                        [
                            "name" => "name",
                            "type" => "string",
                        ],
                    ]
                ],
                "Redirection_Redirection" => [
                    "isNewAllowed" => true,
                    "isExistingAllowed" => true,
                    "key" => ["client", "redirectionid"],
                    "attributes" => [
                        [
                            "name" => "client_id",
                            "type" => "reference",
                            "refersTo" => "Client_Client",
                        ],
                        [
                            "name" => "redirectionid",
                            "type" => "integer",
                        ]
                    ]
                ],
            ],
            "typeMap" => [
                "redirection" => "Redirection_Redirection",
                "client" => "Client_Client",
            ]
        ];
    }

    public function persist()
    {
        $this->findIds();

        $data = $this->intermediateData->getData();

        dump($this->intermediateData->getData());

        foreach ($this->configuration["entityTypes"] as $cypherLabel => $entityTypeConfiguration) {
            $entityType = $this->getEntityType($cypherLabel);
            foreach ($data[$entityType] as $serializedKey => $entity) {
                if ($entity["_id"] === null && false) {
                    $this->createCreateCypher($entityType, $entity);
                } else {
                    $this->createUpdateCypher($entityType, $entity);
                }
//                dump($serializedKey, $entity);
            }
        }
    }

    protected function findIds()
    {
        $data = $this->intermediateData->getData();

//        dump($this->intermediateData->getData());

        foreach ($this->configuration["entityTypes"] as $cypherLabel => $entityTypeConfiguration) {
            $entityType = $this->getEntityType($cypherLabel);
            foreach ($data[$entityType] as $serializedKey => $entity) {

                $neo4jId = $this->getNeo4jId($entityType, $serializedKey);
                $this->intermediateData->setAttribute($entityType, $serializedKey, "_id", $neo4jId);

                $entityExists = ($neo4jId !== null);
                $isNewAllowed = $this->isNewAllowed($entityType);
                $isExistingAllowed = $this->isExistingAllowed($entityType);

//                dump("KEYS", $serializedKey);
//                dump("ENTITY", $entity);
//                dump("IS EXISTING ALLOWED", $isExistingAllowed);
//                dump("IS NEW ALLOWED", $isNewAllowed);

                if ($entityExists && (!$isExistingAllowed)) {
                    throw new ExistingEntityIsNotAllowedException($entityType);
                }

                if (!$entityExists && (!$isNewAllowed)) {
                    throw new NewEntityIsNotAllowedException($entityType);
                }

            }
        }

//        dump($this->intermediateData->getData());
    }

    protected function createCypherToFindByKeys($entityType, $serializedKey)
    {
        $keys = unserialize($serializedKey);

//        dump($entityType, $keys);

        $cypherParts = [];

        $cypherParts[] = sprintf('MATCH (n:%s)', $this->getCypherLabel($entityType));

        foreach ($keys as $name => $value) {
            $attributeType = $this->getAttributeType($entityType, $name);
//            dump("ATTRIBUTE TYPE", $entityType, $name, $attributeType);
            if ($attributeType === "reference") {
                // if key is a reference it should be
                // sprintf('MATCH (n)-[:%s]->()-[:{key}]->({fr: $%s})', $name, $name);
                foreach ($this->getReferredEntityKeys($entityType, $name) as $referredEntityKey) {
                    // getEntityByKeys()
                    $cypherParts[] = sprintf('MATCH (n)-[:%s]->()-[:%s]->({fr: $%s})', $name, $referredEntityKey, $name);
                }
            } else {
                $cypherParts[] = sprintf('MATCH (n)-[:%s]->({fr: $%s})', $name, $name);
            }
        }

        $cypherParts[] = "RETURN ID(n) AS id";

//        dump(implode("\n", $cypherParts));

        return implode("\n", $cypherParts);
    }

    protected function getNeo4jId($entityType, $serializedKey)
    {
        $cypher = $this->createCypherToFindByKeys($entityType, $serializedKey);
        return null;
    }

    protected function getCypherLabel($entityType)
    {
        return $this->configuration["typeMap"][$entityType];
    }

    protected function getEntityType($cypherLabel)
    {
        return array_flip($this->configuration["typeMap"])[$cypherLabel];
    }

    protected function getEntityConfigutation($entityType)
    {
        $cypherLabel = $this->getCypherLabel($entityType);
        return $this->configuration["entityTypes"][$cypherLabel];
    }

    protected function isNewAllowed($entityType)
    {
        return $this->getEntityConfigutation($entityType)["isNewAllowed"];
    }

    protected function isExistingAllowed($entityType)
    {
        return $this->getEntityConfigutation($entityType)["isExistingAllowed"];
    }

    protected function getEntityAttributes($entityType)
    {
        $entityConfiguration = $this->getEntityConfigutation($entityType);
        return $entityConfiguration["attributes"];
    }

    protected function getEntityAttribute($entityType, $attributeName)
    {
        $attributes = $this->getEntityAttributes($entityType);
        $attribute = array_filter($attributes, function ($item) use ($attributeName) {
            return $item["name"] == $attributeName;
        });
        return reset($attribute);
    }

    protected function getAttributeType($entityType, $attributeName)
    {
        $attribute = $this->getEntityAttribute($entityType, $attributeName);
        return $attribute["type"];
    }

    protected function getReferredEntityConfiguration($entityType, $name)
    {
        $referredLabel = $this->getEntityAttribute($entityType, $name)["refersTo"];
        $referredEntityType = $this->getEntityType($referredLabel);
        return $this->getEntityConfigutation($referredEntityType);
    }

    protected function getReferredEntityKeys($entityType, $name)
    {
        return $this->getReferredEntityConfiguration($entityType, $name)["key"];
    }

    public function createCreateCypher($entityType, $entity)
    {
        $entity["sysDesignation"] = "aaa";

        $cypherParts = [];

        $cypherParts[] = sprintf(
            'CREATE (n:section:%s{_type: "%s", uuid: apoc.create.uuid(), fr: $sysDesignation})',
            $this->getCypherLabel($entityType),
            $this->getCypherLabel($entityType)
        );

        // Add references to the query
        $cypherParts = array_merge($cypherParts, $this->findReferences($entityType, $entity));
        // Add attributes to the query
        $cypherParts = array_merge($cypherParts, $this->addAttributes($entityType, $entity));

        $cypherParts[] = 'CREATE (n)-[r_sys_designation:sys_designation]->(a_sys_designation:attribute{fr: $sysDesignation})';
        $cypherParts[] = 'CREATE (n)-[r_sys_created_at:sys_created_at]->(a_sys_created_at:attribute{fr: datetime()})';
        $cypherParts[] = 'CREATE (n)-[r_sys_modified_at:sys_modified_at]->(a_sys_modified_at:attribute{fr: datetime()})';

        $cypher = implode("\n", $cypherParts);

        dump("CREATE QUERY", $entityType, $entity, $cypher);
    }

    protected function findReferences($entityType, $entity)
    {
        $cypherParts = [];

        foreach ($this->getEntityAttributes($entityType) as $attribute) {
            $attributeName = $attribute["name"];
            $attributeValue = $entity[$attributeName];

            if ($attribute["type"] === "reference") {

                $referredEntityType = $this->getEntityType($attribute["refersTo"]);
                $referredEntityId = $this->intermediateData->getEntity(
                    $referredEntityType,
                    $attributeValue
                )["_id"];

                $cypherParts[] = sprintf(
                    'MATCH (%s) WHERE ID(%s) = %d',
                    $attributeName,
                    $attributeName,
                    $referredEntityId
                );
            }
        }

        return $cypherParts;
    }

    protected function addAttributes($entityType, $entity)
    {
        $cypherParts = [];

        foreach ($this->getEntityAttributes($entityType) as $attribute) {

            $attributeName = $attribute["name"];

            if ($attribute["type"] === "reference") {
                $cypherParts[] = sprintf(
                    'CREATE (n)-[r_%s:%s]->(%s)',
                    $attributeName,
                    $attributeName,
                    $attributeName,
                );
            } else {
                $cypherParts[] = sprintf(
                    'CREATE (n)-[r_%s:%s]->(a_%s:attribute{fr: $%s})',
                    $attributeName,
                    $attributeName,
                    $attributeName,
                    $attributeName,
                );
            }
        }

        return $cypherParts;
    }

    public function createUpdateCypher($entityType, $entity)
    {
        dump("UPDATE QUERY", $entityType, $entity);

        $cypherParts = [];

        $cypherParts[] = 'MATCH (n) WHERE ID(n) = $_id';

        foreach ($this->getEntityAttributes($entityType) as $attribute) {
            $attributeName = $attribute["name"];

            if ($attribute["type"] === "reference") {
                $cypherParts[] = sprintf('OPTIONAL MATCH (n)-[r_%s:%s]->()', $attributeName, $attributeName);
            } else {
                $cypherParts[] = sprintf('OPTIONAL MATCH (n)-[:%s]->(%s)', $attributeName, $attributeName);
            }
        }
        $cypherParts[] = 'OPTIONAL MATCH (n)-[:sys_modified_at]->(sys_modified_at)';

        foreach ($this->getEntityAttributes($entityType) as $attribute) {
            $attributeName = $attribute["name"];

            if ($attribute["type"] === "reference") {
                $cypherParts[] = sprintf('DELETE r_%s', $attributeName);
            } else {
                $cypherParts[] = sprintf('DETACH DELETE %s', $attributeName);
            }
        }
        $cypherParts[] = 'DETACH DELETE sys_modified_at';

        $cypherParts[] = 'WITH DISTINCT n';

        // Add references to the query
        $cypherParts = array_merge($cypherParts, $this->findReferences($entityType, $entity));
        // Add attributes to the query
        $cypherParts = array_merge($cypherParts, $this->addAttributes($entityType, $entity));

        $cypherParts[] = 'CREATE (n)-[r_sys_modified_at:sys_modified_at]->(a_sys_modified_at:attribute{fr: datetime()})';

        $cypher = implode("\n", $cypherParts);

        dump($cypher);

        return $cypher;
    }
}
