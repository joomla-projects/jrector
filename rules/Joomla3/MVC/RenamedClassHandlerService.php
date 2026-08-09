<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Rector\Joomla3\MVC;

/**
 * Automatically save the renamed classes, sorted by component side (admin, site).
 */
final class RenamedClassHandlerService
{
    /**
     * The directory where the _classmap.json file will be stored into.
     *
     * @since 1.0.0
     * @var   string
     */
    private $directory;

    /**
     * The temporary instance of the class map
     *
     * @since 1.0.0
     * @var   array[]
     */
    private $map = [
        'site'  => [],
        'admin' => [],
    ];

    /**
     * Public constructor
     *
     * @param   string  $directory  The directory of the _classmap.json file
     *
     * @since   1.0.0
     */
    public function __construct(string $directory)
    {
        $this->directory = $directory;

        $this->load();
    }

    /**
     * Called on service destruction. Auto-saves the class map file.
     *
     * @since  1.0.0
     */
    public function __destruct()
    {
        $this->save();
    }

    /**
     * Adds an entry into the class map
     *
     * @param   string  $legacyClass      The legacy class which we renamed from.
     * @param   string  $namespacedClass  The FQN of the namespaced class we renamed to.
     * @param   string  $namespacePrefix  The namespace prefix we used.
     *
     * @return  void
     * @since   1.0.0
     */
    public function addEntry(string $legacyClass, string $namespacedClass, string $namespacePrefix)
    {
        $prefix   = trim($namespacePrefix, '\\');
        $tempName = trim($namespacedClass, '\\');

        if (strpos($tempName, $prefix) !== 0) {
            return;
        }

        $tempName = trim(substr($tempName, \strlen($prefix)), '\\');
        $parts    = explode('\\', $tempName);

        if (!\in_array($parts[0], ['Administrator', 'Site'])) {
            return;
        }

        $side = $parts[0] === 'Site' ? 'site' : 'admin';

        $this->map[$side][$legacyClass] = $namespacedClass;
    }

    /**
     * Return an old to new classname map for a specific application side
     *
     * @param   string|null  $side  The application side: admin or site.
     *
     * @return  array
     * @since   1.0.0
     */
    public function getOldToNewMap(?string $side = 'admin')
    {
        return $this->map[$side] ?? [];
    }

    /**
     * Load the already saved class map from _classmap.json
     *
     * @return  void
     * @since   1.0.0
     */
    private function load()
    {
        $filePath = $this->directory . '/_classmap.json';

        if (!is_file($filePath)) {
            return;
        }

        $contents = file_get_contents($filePath);
        $decoded  = $contents === false ? null : json_decode($contents, true);

        $this->map = $this->normalise(\is_array($decoded) ? $decoded : []);
    }

    /**
     * Reduces a decoded class map to the expected shape: two sides, each a map of legacy class
     * name to namespaced class name.
     *
     * Files written by the earlier array_merge_recursive() based save() hold an array of
     * identical strings instead of a single string for every entry. Those are repaired here
     * rather than discarded, so an existing _classmap.json keeps working after the upgrade.
     *
     * @param   array  $map  The raw decoded map.
     *
     * @return  array[]
     * @since   1.0.0
     */
    private function normalise(array $map): array
    {
        $normalised = [];

        foreach (['site', 'admin'] as $side) {
            $normalised[$side] = [];

            $entries = $map[$side] ?? [];

            if (!\is_array($entries)) {
                continue;
            }

            foreach ($entries as $legacyClass => $namespacedClass) {
                if (\is_string($namespacedClass)) {
                    $normalised[$side][$legacyClass] = $namespacedClass;

                    continue;
                }

                if (!\is_array($namespacedClass)) {
                    continue;
                }

                $first = reset($namespacedClass);

                if (\is_string($first)) {
                    $normalised[$side][$legacyClass] = $first;
                }
            }
        }

        return $normalised;
    }

    /**
     * Saved the class map into _classmap.json
     *
     * @return  void
     * @since   1.0.0
     */
    private function save()
    {
        $filePath = $this->directory . '/_classmap.json';

        if (is_file($filePath)) {
            $contents = file_get_contents($filePath);
            $decoded  = $contents === false ? null : json_decode($contents, true);

            if (\is_array($decoded)) {
                /**
                 * array_replace_recursive(), NOT array_merge_recursive(): the latter turns two
                 * scalar values stored under the same key into an array holding both. Since
                 * save() merges the file with a map that was loaded from that same file, every
                 * save doubled the length of every entry — which exhausted the memory limit
                 * after a couple of runs and corrupted the values into arrays on the way.
                 */
                $this->map = array_replace_recursive($this->normalise($decoded), $this->map);
            }
        }

        file_put_contents($filePath, json_encode($this->map));
    }
}
