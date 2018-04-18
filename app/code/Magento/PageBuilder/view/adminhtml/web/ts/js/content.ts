/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

import _ from "underscore";
import appearanceConfig from "./component/block/appearance-config";
import {DataObject} from "./component/data-store";
import AttributeFilter from "./component/format/attribute-filter";
import AttributeMapper from "./component/format/attribute-mapper";
import StyleAttributeFilter from "./component/format/style-attribute-filter";
import StyleAttributeMapper from "./component/format/style-attribute-mapper";
import ContentTypeInterface from "./content-type.d";
import ObservableObject from "./observable-object.d";
import ObservableUpdater from "./observable-updater";

export default class Content {
    public data: ObservableObject = {};
    private parent: ContentTypeInterface;
    private observableUpdater: ObservableUpdater;
    /**
     * @deprecated
     */
    private attributeFilter: AttributeFilter = new AttributeFilter();
    /**
     * @deprecated
     */
    private attributeMapper: AttributeMapper =  new AttributeMapper();
    /**
     * @deprecated
     */
    private styleAttributeFilter: StyleAttributeFilter = new StyleAttributeFilter();
    /**
     * @deprecated
     */
    private styleAttributeMapper: StyleAttributeMapper = new StyleAttributeMapper();

    /**
     * @param {ContentTypeInterface} parent
     * @param {ObservableUpdater} observableUpdater
     */
    constructor(
        parent: ContentTypeInterface,
        observableUpdater: ObservableUpdater,
    ) {
        this.parent = parent;
        this.observableUpdater = observableUpdater;
        this.bindEvents();
    }

    /**
     * Retrieve the render template
     *
     * @returns {string}
     */
    get renderTemplate(): string {
        return appearanceConfig(this.parent.config.name, this.getData().appearance).render_template;
    }

    /**
     * Get data for css binding, example {"class-name": true}
     *
     * @returns {DataObject}
     * @deprecated
     */
    public getCss(element: string) {
        const result: object = {};
        let css: string = "";
        const data = this.parent.store.get(this.parent.id);
        if (element === undefined) {
            if ("css_classes" in data && data.css_classes !== "") {
                css = data.css_classes;
            }
        } else {
            const config = appearanceConfig(this.parent.config.name, data.appearance).data_mapping.elements[element];
            if (config.css && config.css.var !== undefined && config.css.var in data) {
                css = data[config.css.var];
            }
        }
        if (css) {
            css.toString().split(" ").map(
                (value: any, index: number) => result[value] = true,
            );
        }
        return result;
    }

    /**
     * Get data for style binding, example {"backgroundColor": "#cccccc"}
     *
     * @returns {DataObject}
     * @deprecated
     */
    public getStyle(element: string) {
        let data = _.extend({}, this.parent.store.get(this.parent.id), this.parent.config);
        if (element === undefined) {
            if (typeof data.appearance !== "undefined" &&
                typeof data.appearances !== "undefined" &&
                typeof data.appearances[data.appearance] !== "undefined") {
                _.extend(data, data.appearances[data.appearance]);
            }
            return this.styleAttributeMapper.toDom(this.styleAttributeFilter.filter(data));
        }

        const appearanceConfiguration = appearanceConfig(this.parent.config.name, data.appearance);
        const config = appearanceConfiguration.data_mapping.elements;

        data = this.observableUpdater.convertData(data, appearanceConfiguration.data_mapping.converters);

        let result = {};
        if (config[element].style.length) {
            result = this.observableUpdater.convertStyle(config[element], data, "master");
        }
        return result;
    }

    /**
     * Get data for attr binding, example {"data-role": "element"}
     *
     * @returns {DataObject}
     * @deprecated
     */
    public getAttributes(element: string) {
        let data = _.extend({}, this.parent.store.get(this.parent.id), this.parent.config);
        if (element === undefined) {
            if (undefined === data.appearance || !data.appearance) {
                data.appearance = undefined !== this.parent.config.fields.appearance
                    ? this.parent.config.fields.appearance.default
                    : "default";
            }
            return this.attributeMapper.toDom(this.attributeFilter.filter(data));
        }

        const appearanceConfiguration = appearanceConfig(this.parent.config.name, data.appearance);
        const config = appearanceConfiguration.data_mapping.elements;

        data = this.observableUpdater.convertData(data, appearanceConfiguration.data_mapping.converters);

        let result = {};
        if (config[element].attributes.length) {
            result = this.observableUpdater.convertAttributes(config[element], data, "master");
        }

        return result;
    }

    /**
     * Get data for html binding
     *
     * @param {string} element
     * @returns {object}
     * @deprecated
     */
    public getHtml(element: string) {
        const data = this.parent.store.get(this.parent.id);
        const config = appearanceConfig(this.parent.config.name, data.appearance).data_mapping.elements[element];
        let result = "";
        if (undefined !== config.html.var) {
            result = this.observableUpdater.convertHtml(config, data, "master");
        }
        return result;
    }

    /**
     * Get block data
     *
     * @param {string} element
     * @returns {DataObject}
     * @deprecated
     */
    public getData(element: string) {
        let data = _.extend({}, this.parent.store.get(this.parent.id));

        if (undefined === element) {
            return data;
        }

        const appearanceConfiguration = appearanceConfig(this.parent.config.name, data.appearance);
        const config = appearanceConfiguration.data_mapping.elements;

        data = this.observableUpdater.convertData(data, appearanceConfiguration.data_mapping.converters);

        const result = {};
        if (undefined !== config[element].tag.var) {
            result[config[element].tag.var] = data[config[element].tag.var];
        }
        return result;
    }

    /**
     * Attach event to updating data in data store to update observables
     */
    private bindEvents(): void {
        this.parent.store.subscribe(
            (data: DataObject) => {
                this.observableUpdater.update(
                    this,
                    _.extend({name: this.parent.config.name}, this.parent.store.get(this.parent.id)),
                );
            },
            this.parent.id,
        );
    }
}
