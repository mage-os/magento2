/**
 * - Config.js
 * Handles retrieve the configuration from the server
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
define(['bluefoot/jquery', 'bluefoot/ajax'], function (jQuery, AjaxClass) {

    /**
     * The initial config before the Ajax request
     *
     * @type {{}}
     * @private
     */
    var _initConfig = {};

    /**
     * Cache the config within this module
     *
     * @type {boolean}
     * @private
     */
    var _config = {};

    /**
     * Store all the fields in a cache so we don't have to re-generate them
     *
     * @type {boolean}
     * @private
     */
    var _allFields = false;

    return {

        /**
         * Set the initial config
         *
         * @param config
         */
        setInitConfig: function(config) {
            _initConfig = config;
        },

        /**
         * Return the initial config
         *
         * @returns {{}}
         */
        getInitConfig: function (key) {
            if (key) {
                if (typeof _initConfig[key] !== 'undefined') {
                    return _initConfig[key];
                }
                return null;
            }
            return _initConfig;
        },

        /**
         * Retrieve the full configuration
         *
         * @param callback
         * @param entityIds
         * @param storeId
         */
        initConfig: function (callback, entityIds, storeId) {
            var params = {
                storeId: storeId
            };
            if (typeof entityIds === 'object' && entityIds.length > 0) {
                params.entityIds = entityIds;
            }

            // Include the Ajax Class
            var Ajax = new AjaxClass(this.getInitConfig('formkey'));
            Ajax.post(this.getInitConfig('config_url'), params, function (data) {
                if (typeof _config.entities === 'object') {
                    _config.entities = jQuery.extend(_config.entities, data.entities);
                } else {
                    // Merge the two configuration objects
                    _config = jQuery.extend(this.getInitConfig(), data);
                }
                if (typeof callback === 'function') {
                    callback(_config);
                }
            }.bind(this), false, function () {
                return require('bluefoot/modal').alert('An issue has occurred whilst attempting to load the Blue Foot configuration, please contact your development team.');
            });
        },

        /**
         * Return a content types configuration from the config
         *
         * @param type
         * @returns {*}
         */
        getContentTypeConfig: function (type) {
            if (typeof _config.contentTypes === 'object' && typeof _config.contentTypes[type] === 'object') {
                return _config.contentTypes[type];
            }

            return false;
        },

        /**
         * Retrieve the previously built configuration
         *
         * @returns {boolean}
         */
        getConfig: function () {
            return _config;
        },

        /**
         * Return a value from the config
         *
         * @param key
         * @returns {*}
         */
        getValue: function (key) {
            if (typeof _config[key] !== 'undefined') {
                return _config[key];
            }
            if (typeof this.getInitConfig()[key] !== 'undefined') {
                return this.getInitConfig()[key];
            }
            return null;
        },
        /**
         * Deletes a value from the config providing you know the config key, the item key and the value to check for
         *
         * @param key
         * @param valueKey
         * @param value
         */
        deleteValue: function (key,valueKey,value) {
            var arr = [];
            _config[key].forEach(function(item){
                if (item[valueKey] != value)
                {
                    arr.push(item);
                }
            });
            _config[key] = arr;
        },
        /**
         * Add to a config setting array without having to rebuild config.
         * @param key
         * @param values (array)
         */
        mergeValues: function (key,values)
        {
            _config[key] = _config[key].concat(values);
        },
        /**
         * Update a value in the tempalates array
         * @param matchKey
         * @param matchValue
         * @param newValueKey
         * @param newValue
         */
        updateTemplateValue: function (matchKey,matchValue,newValueKey,newValue)
        {
            var arr = [];
            _config['templates'].forEach(function(item){
                if (item[matchKey] === matchValue)
                {
                    item[newValueKey] = newValue;
                }
                arr.push(item);
            });
            _config['templates'] = arr;
        },

        /**
         * Plugins can have config values declared inside their JSON
         *
         * @param plugin
         * @param key
         * @returns {*}
         */
        getPluginConfig: function (plugin, key) {
            var config = this.getConfig();
            if (typeof config.plugins[plugin] !== 'undefined' && typeof config.plugins[plugin]['config'] !== 'undefined' && typeof config.plugins[plugin]['config'][key] !== 'undefined') {
                return config.plugins[plugin]['config'][key];
            }

            return null;
        },

        /**
         * Return all fields available in the system
         *
         * @returns {{}}
         */
        getAllFields: function () {
            if (_allFields !== false) {
                return _allFields;
            }

            _allFields = {};
            jQuery.each(_config.contentTypes, function (index, element) {
                if (typeof element.fields === 'object') {
                    jQuery.extend(_allFields, element.fields);
                }
            });

            // Include global fields in all fields
            if (this.getValue('globalFields')) {
                jQuery.extend(_allFields, this.getValue('globalFields'));
            }

            return _allFields;
        },

        /**
         * Return an individual fields data
         *
         * @param key
         * @returns {*}
         */
        getField: function (key) {
            var fields;
            if (_allFields === false) {
                fields = this.getAllFields();
            } else {
                fields = _allFields;
            }

            if (typeof fields[key] !== 'undefined') {
                return fields[key];
            }

            return null;
        },

        /**
         * Return the form key
         *
         * @returns {*}
         */
        getFormKey: function () {
            return this.getInitConfig().form_key;
        },

        /**
         * Reset the configuration
         */
        resetConfig: function () {
            _config = {};
        },

        /**
         * Retrieve the store ID
         * @returns {*}
         */
        getStoreId: function () {
            if (jQuery('#store_switcher').length > 0) {
                return jQuery('#store_switcher').val();
            }
        }
    };
});