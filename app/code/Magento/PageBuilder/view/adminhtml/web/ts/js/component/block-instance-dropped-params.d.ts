/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

import ContentTypeInterface from "../content-type.d"

export default interface BlockInstanceDroppedParamsInterface {
    parent: ContentTypeInterface;
    blockInstance: ContentTypeInterface;
    index?: number;
    stageId: string;
}
