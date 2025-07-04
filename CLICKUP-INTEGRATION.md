# Integração com ClickUp - Dashboard F2F

## Como exportar dados do ClickUp

### Método 1: Time Tracking Reports (Recomendado)

1. **Acesse o ClickUp** e vá para a seção **Time Tracking**
2. Clique em **Reports** no menu lateral
3. **Configure o período**:
   - Selecione as datas de início e fim
   - Escolha os projetos/spaces desejados
4. **Exporte os dados**:
   - Clique no botão **Export**
   - Selecione **CSV** ou **Excel** como formato
   - Aguarde o download do arquivo

### Método 2: Task Lists

1. **Acesse uma List** no ClickUp
2. Clique nos **três pontos** (⋯) no canto superior direito
3. Selecione **Export** → **CSV**
4. Escolha as colunas que deseja exportar
5. Faça o download do arquivo

## Colunas Suportadas

O sistema detecta automaticamente planilhas do ClickUp baseado nas seguintes colunas:

### Colunas Obrigatórias (pelo menos uma deve estar presente):
- `User ID`
- `Time Entry ID`
- `Space ID`
- `Task ID`

### Colunas Opcionais Reconhecidas:
- `Username` - Nome do usuário responsável
- `Description` - Descrição da tarefa
- `Task Name` - Nome da tarefa
- `Space Name` - Nome do projeto/espaço
- `Folder Name` - Nome da pasta
- `List Name` - Nome da lista
- `Status` - Status da tarefa
- `Start` / `Stop` - Horários de início e fim
- `Time Tracked` - Tempo rastreado
- `Due Date` - Data de vencimento
- `Assignees` - Responsáveis
- `Priority` - Prioridade
- `Tags` - Etiquetas

## Como o Sistema Mapeia os Dados

### Projeto (Project Name)
Prioridade de mapeamento:
1. `Space Name` (preferencial)
2. `Folder Name`
3. `List Name`
4. "Projeto Sem Nome" (fallback)

### Tarefa (Task Name)
Prioridade de mapeamento:
1. `Task Name` (preferencial)
2. `Description`
3. "Tarefa Sem Nome" (fallback)

### Status
Prioridade de mapeamento:
1. `Status` (se disponível diretamente)
2. Baseado em `Start` e `Stop`:
   - Se tem `Stop` e `Start`: "Concluído"
   - Se não tem `Start`: "Pendente"
   - Caso contrário: "Em Andamento"

### Progresso
Calculado baseado no `Time Tracked`:
- 8 horas = 100% de progresso
- Valores proporcionais para menos tempo

### Responsável (Assigned To)
Prioridade de mapeamento:
1. `Username`
2. `Assignees`

### Data de Vencimento
Prioridade de mapeamento:
1. `Due Date`
2. `Stop` (data de conclusão)

## Solução de Problemas

### "Planilha processada mas nenhum dado aparece"

**Possíveis causas:**
1. **Formato não reconhecido**: O arquivo não contém colunas típicas do ClickUp
2. **Dados vazios**: As linhas não têm informações suficientes
3. **Encoding do arquivo**: Problemas de codificação de caracteres

**Soluções:**
1. Verifique se o arquivo CSV contém pelo menos uma das colunas obrigatórias
2. Abra o arquivo CSV em um editor de texto para verificar o conteúdo
3. Certifique-se de que o arquivo foi exportado diretamente do ClickUp
4. Tente exportar novamente com todas as colunas disponíveis

### "Formato ClickUp não detectado"

**Verificações:**
1. O arquivo deve ser CSV (não Excel)
2. Deve conter cabeçalhos na primeira linha
3. Deve ter pelo menos uma coluna obrigatória do ClickUp

### "Dados incorretos ou incompletos"

**Verificações:**
1. Certifique-se de que selecionou o período correto no ClickUp
2. Verifique se todos os projetos necessários foram incluídos na exportação
3. Confirme se as tarefas têm as informações necessárias preenchidas no ClickUp

## Modo Debug

Para ativar informações detalhadas de debug:

1. Adicione esta linha no arquivo `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   ```

2. Após fazer upload de uma planilha, você verá informações detalhadas sobre:
   - Colunas detectadas
   - Se o formato ClickUp foi reconhecido
   - Como a primeira linha foi mapeada
   - Estatísticas de processamento

## Formatos de Data Suportados

O sistema reconhece automaticamente os seguintes formatos de data:
- `Y-m-d H:i:s` (2024-01-15 14:30:00)
- `Y-m-d` (2024-01-15)
- `m/d/Y H:i:s` (01/15/2024 14:30:00)
- `m/d/Y` (01/15/2024)
- `d/m/Y H:i:s` (15/01/2024 14:30:00)
- `d/m/Y` (15/01/2024)
- ISO 8601 com timezone

## Limitações Atuais

- **Tamanho do arquivo**: Recomendado até 10MB
- **Registros exibidos**: Máximo de 100 registros na tabela (todos são importados)
- **Campos customizados**: Campos personalizados do ClickUp são armazenados mas não mapeados automaticamente
- **Arquivos XLS**: Apenas XLSX e CSV são suportados (XLS legado não é suportado)

## Próximas Funcionalidades

- [ ] Mapeamento de campos customizados
- [ ] Sincronização automática via API do ClickUp
- [ ] Filtros avançados na visualização
- [ ] Exportação de relatórios personalizados
- [ ] Suporte para múltiplas planilhas em um arquivo XLSX