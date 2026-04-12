<template>
    <div class="container">
        <div class="section">
            <NcSettingsSection
                class="admin-settings-section"
                name="Rotating Wallpapers"
                :description="t('rotatingwallpapers', 'Set custom templates for the document generation')" />

                <h4>{{ t('rotatingwallpapers', 'Images') }}</h4>

                <div class="image-list">
                    <WallpaperImage
                        v-for="img in images"
                        :key="img.filename"
                        :src="img.url"
                        @remove="removeImageWithSync(img.filename)" />
                </div>

                <input
                    ref="uploadFileInput"
                    type="file"
                    accept="image/*"
                    class="hidden-file-input"
                    @change="uploadImage">
                <NcButton @click="uploadFileInput?.click()">
                    {{ t('rotatingwallpapers', 'Upload image') }}
                </NcButton>

                <h4>{{ t('rotatingwallpapers', 'Rotation') }}</h4>
                <p>{{ t('rotatingwallpapers', 'How often should the images change?') }}</p>
                <NcRadioGroup v-model="rotationMode" class="radio-group" label="Rotation" label-hidden>
                    <NcCheckboxRadioSwitch type="radio" value="every10sec">
                        {{ t('rotatingwallpapers', 'Every 10 seconds (testing purposes)') }}
                    </NcCheckboxRadioSwitch>
                    <NcCheckboxRadioSwitch type="radio" value="every5min">
                        {{ t('rotatingwallpapers', 'Every 5 minutes') }}
                    </NcCheckboxRadioSwitch>
                    <NcCheckboxRadioSwitch type="radio" value="every30min">
                        {{ t('rotatingwallpapers', 'Every 30 minutes') }}
                    </NcCheckboxRadioSwitch>
                    <NcCheckboxRadioSwitch type="radio" value="every1h">
                        {{ t('rotatingwallpapers', 'Every hour') }}
                    </NcCheckboxRadioSwitch>
                    <NcCheckboxRadioSwitch type="radio" value="every3h">
                        {{ t('rotatingwallpapers', 'Every 3 hours') }}
                    </NcCheckboxRadioSwitch>
                    <NcCheckboxRadioSwitch type="radio" value="daily">
                        {{ t('rotatingwallpapers', 'Daily') }}
                    </NcCheckboxRadioSwitch>
                </NcRadioGroup>

                <h4>{{ t('rotatingwallpapers', 'Users') }}</h4>
                <p>{{ t('rotatingwallpapers', 'It is recommended to disable user customization') }}</p>
                <NcButton @click="navigateToTheming()">{{ t('rotatingwallpapers', 'Navigate to Theming-Settings') }}</NcButton>

                <h4>{{ t('rotatingwallpapers', 'Public') }}</h4>
                <p>{{ t('rotatingwallpapers', 'Set a dedicated image for all guests') }}</p>

                <div class="image-list">
                    <WallpaperImage
                        v-if="publicWallpaper"
                        :src="publicWallpaper.url"
                        @remove="removePublicWallpaper()" />
                </div>

                <input
                    ref="publicUploadFileInput"
                    type="file"
                    accept="image/*"
                    class="hidden-file-input"
                    @change="uploadPublicWallpaper">
                <NcButton @click="publicUploadFileInput?.click()">
                    {{ t('rotatingwallpapers', 'Upload image') }}
                </NcButton>

        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import WallpaperImage from '../components/WallpaperImage.vue'
import { useWallpapers } from '../composables/useWallpapers'
import { useRotationSettings } from '../composables/useRotationSettings'
import { usePublicSettings } from '../composables/usePublicSettings'

const { images, uploadFileInput, loadWallpapers, uploadImage, removeImage } = useWallpapers()
const { rotationMode, loadRotationSettings } = useRotationSettings()
const { publicWallpaper, publicUploadFileInput, loadPublicSettings, uploadPublicWallpaper, removePublicWallpaper } = usePublicSettings()

function removeImageWithSync(filename: string) {
	removeImage(filename, () => { publicWallpaper.value = null }, publicWallpaper.value?.filename)
}

function navigateToTheming() {
	window.location.href = generateUrl('/settings/admin/theming')
}

onMounted(() => Promise.all([loadWallpapers(), loadRotationSettings(), loadPublicSettings()]))
</script>

