<?php
    /**
     * Copyright (c) 2010-2012 Arne Blankerts <arne@blankerts.de>
     * All rights reserved.
     *
     * Redistribution and use in source and binary forms, with or without modification,
     * are permitted provided that the following conditions are met:
     *
     *   * Redistributions of source code must retain the above copyright notice,
     *     this list of conditions and the following disclaimer.
     *
     *   * Redistributions in binary form must reproduce the above copyright notice,
     *     this list of conditions and the following disclaimer in the documentation
     *     and/or other materials provided with the distribution.
     *
     *   * Neither the name of Arne Blankerts nor the names of contributors
     *     may be used to endorse or promote products derived from this software
     *     without specific prior written permission.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
     * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT  * NOT LIMITED TO,
     * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
     * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER ORCONTRIBUTORS
     * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
     * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
     * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
     * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
     * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
     * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
     * POSSIBILITY OF SUCH DAMAGE.
     *
     * @package    phpDox
     * @author     Arne Blankerts <arne@blankerts.de>
     * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
     * @license    BSD License
     */
namespace TheSeer\phpDox\Collector {

    use TheSeer\fDOM\fDOMDocument;
    use TheSeer\fDOM\fDOMElement;
    use TheSeer\phpDox\ProgressLogger;
    use TheSeer\phpDox\Project\Dependency;
    use TheSeer\phpDox\Project\Project;
    use TheSeer\phpDox\Project\AbstractUnitObject;
    use TheSeer\phpDox\InheritanceConfig;
    use TheSeer\phpDox\Project\ProjectException;

    /**
     * Inheritance resolving class
     */
    class InheritanceResolver {

        /**
         * @var \TheSeer\phpDox\ProgressLogger
         */
        private $logger;

        /**
         * @var Project
         */
        private $project;

        /**
         * @var InheritanceConfig
         */
        private $config;

        private $dependencyStack = array();

        /**
         * @var array
         */
        private $unresolved = array();

        public function __construct(ProgressLogger $logger) {
            $this->logger = $logger;
        }

        public function resolve(Array $changed, Project $project, InheritanceConfig $config) {
            //return;

            if (count($changed) == 0) {
                return;
            }
            $this->logger->reset();
            $this->logger->log("Resolving inheritance\n");

            $this->project = $project;
            $this->config = $config;

            $this->setupDependencies();

            foreach($changed as $unit) {
                $this->logger->progress('processed');
                /** @var AbstractUnitObject $unit */
                if ($unit->hasExtends()) {
                    try {
                        $extends = $unit->getExtends();
                        $extendedUnit = $this->getUnitByName($extends);
                        $this->processExtends($unit, $extendedUnit);
                    } catch (ProjectException $e) {
                        $this->unresolved[$unit->getName()] = $extends;
                    }
                }
            }

            $this->project->save();
            $this->logger->completed();
        }

        public function hasUnresolved() {
            return count($this->unresolved) > 0;
        }

        public function getUnresolved() {
            return $this->unresolved;
        }

        private function processExtends(AbstractUnitObject $unit, AbstractUnitObject $extends) {
            $this->project->registerForSaving($unit);
            $this->project->registerForSaving($extends);

            $extends->addExtender($unit);
            $unit->importExports($extends);

            if ($extends->hasExtends()) {
                try {
                    $extendedUnit = $this->getUnitByName($extends->getExtends());
                    $this->processExtends($unit, $extendedUnit, $extendedUnit);
                } catch (ProjectException $e) {
                    $this->unresolved[$unit->getName()] = $extends->getExtends();
                }
            }
        }

        private function setupDependencies() {
            $this->dependencyStack = array(
                $this->project,
            );
            foreach($this->config->getDependencyDirectories() as $depDir) {
                $idxName = $depDir . '/index.xml';
                if (!file_exists($idxName)) {
                    $this->logger->log("'$idxName' not found - skipping dependency");
                    continue;
                }   
                $dom = new fDOMDocument();
                $dom->load($idxName);
                $this->dependencyStack[] = new Dependency($dom, $this->project);
            }
        }

        private function getUnitByName($name) {
            foreach($this->dependencyStack as $dependency) {
                try {
                    $res = $dependency->getUnitByName($name);
                    if ($res) {
                        return $res;
                    }
                } catch (\Exception $e) {}
            }
            throw new ProjectException("No unit with name '$name' found");
        }

    }

}