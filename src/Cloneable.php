<?php namespace Bkwld\Cloner;

// Deps
use App;

/**
 * Mixin accessor methods, callbacks, and the duplicate() helper into models.
 */
trait Cloneable {

	/**
	 * Return the list of attributes on this model that should be cloned
	 * 
	 * @return  array
	 */
	public function getCloneExemptAttributes() {

		// Alwyas make the id and timestamps exempt
		$defaults = [
			$this->getKeyName(),
			$this->getCreatedAtColumn(),
			$this->getUpdatedAtColumn(),
		];

		// It none specified, just return the defaults, else, merge them
		if (!isset($this->clone_exempt_attributes)) return $defaults;
		return array_merge($defaults, $this->clone_exempt_attributes);
	}

	/**
	 * Return a list of attributes that reference files that should be duplicated
	 * when the model is cloned
	 *
	 * @return  array 
	 */
	public function getCloneableFileAttributes() {
		if (!isset($this->cloneable_file_attributes)) return [];
		return $this->cloneable_file_attributes;
	}

	/**
	 * Return the list of relations on this model that should be cloned
	 *
	 * @return  array 
	 */
	public function getCloneableRelations() {
		if (!isset($this->cloneable_relations)) return [];
		return $this->cloneable_relations;
	}

	/**
	 * Clone the current model instance
	 *
	 * @return Illuminate\Database\Eloquent\Model The new, saved clone
	 */
	public function duplicate() {
		return App::make('cloner')->duplicate($this);
	}

	/**
	 * A no-op callback that gets fired when a model is cloning but before it gets
	 * committed to the database
	 * 
	 * @return  void
	 */
	public function onCloning() {}

	/**
	 * A no-op callback that gets fired when a model is cloned and saved to the
	 * database
	 * 
	 * @return  void
	 */
	public function onCloned() {}

}
